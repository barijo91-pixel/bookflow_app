<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 책 표지 사진 → 도서 정보(제목/저자/출판사/ISBN) 인식.
 * Claude(Anthropic) Vision API 호출 + 사용자별 사용량/비용을 ai_usage_logs에 기록.
 *
 * - API 키(site_settings: anthropic_api_key) 미입력 시 호출을 건너뛰고 skipped 로깅 (운영 안전).
 * - 사용자별 월 인식 한도(ai_book_recognition_monthly_limit) 초과 시 차단.
 * - 비용은 응답 usage(토큰 수)에 모델 단가 × 환율을 곱해 추정(원).
 *
 * 실결제/실연동 전 골격 — 모델 기본값은 가장 저렴한 Haiku.
 */
class BookVisionService
{
    /** 모델별 단가 (USD per 1M tokens) — 비용 추정용 */
    public const PRICING = [
        'claude-haiku-4-5'  => ['in' => 1.0, 'out' => 5.0],
        'claude-sonnet-4-6' => ['in' => 3.0, 'out' => 15.0],
        'claude-opus-4-8'   => ['in' => 5.0, 'out' => 25.0],
    ];

    public const DEFAULT_MODEL = 'claude-haiku-4-5';

    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const PROMPT = "이 이미지는 책 표지입니다. 책의 제목/저자/출판사/ISBN(보이면)을 추출해 "
        . "아래 JSON 형식으로만 답하세요. 모르는 항목은 null. 설명 없이 JSON만 출력:\n"
        . '{"title": "", "author": "", "publisher": "", "isbn": null}';

    /**
     * 책 표지 이미지 인식.
     *
     * @param string   $imageBase64 base64 인코딩 이미지 데이터
     * @param string   $mediaType   image/jpeg | image/png | image/webp | image/gif
     * @param int|null $userId      호출 사용자 (사용량 귀속)
     * @return array{ok: bool, reason: ?string, data: ?array, usage?: array, raw?: string}
     */
    public static function recognize(string $imageBase64, string $mediaType = 'image/jpeg', ?int $userId = null): array
    {
        $apiKey = setting('anthropic_api_key');
        $model  = setting('ai_vision_model', self::DEFAULT_MODEL);

        // 1) API 키 미설정 → 건너뜀 (운영 안전: 기능 미활성)
        if (! $apiKey) {
            self::log($userId, $model, 0, 0, 0, 0, 'skipped', ['reason' => 'no_api_key']);
            return ['ok' => false, 'reason' => 'AI 인식이 아직 설정되지 않았습니다. (관리자 > 사이트 설정 > AI 인식에서 API 키 입력)', 'data' => null];
        }

        // 2) 사용자별 월 한도 체크
        $limit = (int) setting('ai_book_recognition_monthly_limit', '100');
        if ($userId && $limit > 0) {
            $used = DB::table('ai_usage_logs')
                ->where('user_id', $userId)
                ->where('type', 'book_recognition')
                ->where('status', 'success')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
            if ($used >= $limit) {
                self::log($userId, $model, 0, 0, 0, 0, 'error', ['reason' => 'monthly_limit', 'limit' => $limit]);
                return ['ok' => false, 'reason' => "이번 달 인식 한도({$limit}건)를 초과했습니다.", 'data' => null];
            }
        }

        // 3) 호출
        try {
            $resp = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(40)->post(self::API_URL, [
                'model'      => $model,
                'max_tokens' => 512,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $imageBase64]],
                        ['type' => 'text', 'text' => self::PROMPT],
                    ],
                ]],
            ]);

            if (! $resp->successful()) {
                self::log($userId, $model, 0, 0, 0, 0, 'error', ['http' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 300)]);
                return ['ok' => false, 'reason' => 'AI 호출 실패 (HTTP ' . $resp->status() . ')', 'data' => null];
            }

            $json      = $resp->json();
            $usage     = $json['usage'] ?? [];
            $in        = (int) ($usage['input_tokens'] ?? 0);
            $out       = (int) ($usage['output_tokens'] ?? 0);
            $cacheRead = (int) ($usage['cache_read_input_tokens'] ?? 0);
            $cost      = self::estimateCost($model, $in, $out);

            $text = '';
            foreach (($json['content'] ?? []) as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= $block['text'];
                }
            }
            $data = self::parseBookInfo($text);

            self::log($userId, $model, $in, $out, $cacheRead, $cost, 'success', ['parsed' => $data !== null]);

            return [
                'ok'     => true,
                'reason' => null,
                'data'   => $data,
                'raw'    => $text,
                'usage'  => ['input_tokens' => $in, 'output_tokens' => $out, 'cost_krw' => $cost],
            ];
        } catch (\Throwable $e) {
            Log::warning('BookVisionService 호출 오류: ' . $e->getMessage());
            self::log($userId, $model, 0, 0, 0, 0, 'error', ['exception' => mb_substr($e->getMessage(), 0, 300)]);
            return ['ok' => false, 'reason' => 'AI 호출 중 오류가 발생했습니다.', 'data' => null];
        }
    }

    /** 토큰 수 × 모델 단가 × 환율 → 추정 비용(원) */
    public static function estimateCost(string $model, int $inputTokens, int $outputTokens): int
    {
        $p   = self::PRICING[$model] ?? self::PRICING[self::DEFAULT_MODEL];
        $usd = ($inputTokens / 1_000_000 * $p['in']) + ($outputTokens / 1_000_000 * $p['out']);
        $rate = (float) setting('ai_usd_to_krw', '1400');
        return (int) round($usd * $rate);
    }

    /** 응답 텍스트에서 JSON 블록 추출 → 배열 */
    private static function parseBookInfo(string $text): ?array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return [
                    'title'     => $decoded['title']     ?? null,
                    'author'    => $decoded['author']    ?? null,
                    'publisher' => $decoded['publisher'] ?? null,
                    'isbn'      => $decoded['isbn']       ?? null,
                ];
            }
        }
        return null;
    }

    /** 사용량 1행 기록 */
    private static function log(?int $userId, string $model, int $in, int $out, int $cacheRead, int $cost, string $status, array $meta = []): void
    {
        DB::table('ai_usage_logs')->insert([
            'user_id'           => $userId,
            'type'              => 'book_recognition',
            'model'             => $model,
            'input_tokens'      => $in,
            'output_tokens'     => $out,
            'cache_read_tokens' => $cacheRead,
            'est_cost_krw'      => $cost,
            'status'            => $status,
            'meta_json'         => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}
