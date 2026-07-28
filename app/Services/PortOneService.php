<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PortOne V2 결제 검증 서비스 — api.portone.io 기준
 *
 * 필수 site_settings (integration 그룹):
 *  - portone_v2_store_id:    Store ID (store-xxxxxxxx)
 *  - portone_v2_channel_key: 결제 채널 키 (channel-key-xxxxxxxx)
 *  - portone_v2_api_secret:  V2 API Secret (서버 검증/취소용)
 *  - portone_active:         'Y' = PG 활성화 (미설정 시 mock 결제로 fallback)
 *
 * V2는 V1(아임포트)과 달리 토큰 발급 없이 'Authorization: PortOne {API_SECRET}' 헤더로 호출.
 * 결제 식별자: paymentId (가맹점이 생성, 브라우저 SDK requestPayment에 전달).
 */
class PortOneService
{
    private const API_BASE = 'https://api.portone.io';

    /** 키 설정 여부 — false면 mock 결제로 fallback. 채널(카드/카카오)이 최소 1개 있어야 활성. */
    public static function isActive(): bool
    {
        return in_array((string) setting('portone_active'), ['1', 'Y', 'true'], true)
            && setting('portone_v2_store_id')
            && setting('portone_v2_api_secret')
            && ! empty(self::methods());
    }

    /**
     * 사용 가능한 결제수단 목록 — 채널키가 설정된 것만.
     * 반환: [['id','label','icon','payMethod','channelKey'], ...]
     *   - 카드(이니시스 등): portone_channel_card  → payMethod CARD
     *   - 카카오페이:        portone_channel_kakao → payMethod EASY_PAY
     * 새 설정이 둘 다 비어있으면 구버전 단일 채널(portone_v2_channel_key + portone_pay_method)로 폴백.
     */
    public static function methods(): array
    {
        $card  = trim((string) setting('portone_channel_card', ''));
        $kakao = trim((string) setting('portone_channel_kakao', ''));

        $out = [];
        // 채널키가 있고 + 표시 토글이 켜져 있어야 노출 (토글로 채널키 보존한 채 숨김 가능)
        if ($card !== '' && self::channelEnabled('portone_card_enabled')) {
            $out[] = ['id' => 'card', 'label' => '카드', 'icon' => 'credit-card',
                      'payMethod' => 'CARD', 'channelKey' => $card];
        }
        if ($kakao !== '' && self::channelEnabled('portone_kakao_enabled')) {
            $out[] = ['id' => 'kakao', 'label' => '카카오페이', 'icon' => 'chat-fill',
                      'payMethod' => 'EASY_PAY', 'channelKey' => $kakao];
        }

        // 구버전 단일 채널 호환
        if (empty($out)) {
            $legacy = trim((string) setting('portone_v2_channel_key', ''));
            if ($legacy !== '') {
                $pm = self::payMethod();
                $out[] = ['id' => 'default',
                          'label'      => $pm === 'EASY_PAY' ? '카카오페이' : '카드',
                          'icon'       => $pm === 'EASY_PAY' ? 'chat-fill' : 'credit-card',
                          'payMethod'  => $pm,
                          'channelKey' => $legacy];
            }
        }
        return $out;
    }

    public static function storeId(): string
    {
        return (string) setting('portone_v2_store_id', '');
    }

    public static function channelKey(): string
    {
        return (string) setting('portone_v2_channel_key', '');
    }

    /** 채널 표시 토글 — 기본 켜짐. '0'/''/false/N 이면 숨김 */
    private static function channelEnabled(string $key): bool
    {
        return ! in_array((string) setting($key, '1'), ['0', '', 'false', 'N'], true);
    }

    /**
     * 브라우저 결제창 payMethod — 채널(PG)에 맞춰 관리자에서 설정.
     *  - CARD:     카드 결제창 (KG이니시스 등 일반 PG)
     *  - EASY_PAY: 간편결제 (카카오페이) — 카카오페이 채널은 반드시 EASY_PAY
     * 잘못된 값이면 CARD로 폴백.
     */
    public static function payMethod(): string
    {
        $m = strtoupper(trim((string) setting('portone_pay_method', 'CARD')));
        return in_array($m, ['CARD', 'EASY_PAY'], true) ? $m : 'CARD';
    }

    private static function secret(): string
    {
        return (string) setting('portone_v2_api_secret', '');
    }

    /**
     * 결제 단건 조회 — paymentId 기준
     * 반환(V2): ['status' => 'PAID'|'FAILED'|..., 'amount' => ['total' => int, ...], 'orderName' => ..., ...]
     */
    public static function getPayment(string $paymentId): ?array
    {
        $secret = self::secret();
        if (! $secret) return null;

        try {
            $res = Http::withHeaders(['Authorization' => 'PortOne ' . $secret])
                ->get(self::API_BASE . '/payments/' . rawurlencode($paymentId));
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning('PortOne v2 getPayment failed', ['status' => $res->status(), 'body' => $res->body()]);
        } catch (\Throwable $e) {
            Log::error('PortOne v2 getPayment exception', ['err' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * 결제 상태가 완료(PAID)인지 + 결제 총액 반환
     * 반환: ['paid' => bool, 'amount' => int, 'status' => string]
     */
    public static function paidAmount(?array $payment): array
    {
        $status = is_array($payment) ? ($payment['status'] ?? '') : '';
        $amount = is_array($payment) ? (int) ($payment['amount']['total'] ?? 0) : 0;
        return ['paid' => $status === 'PAID', 'amount' => $amount, 'status' => $status];
    }

    /**
     * 결제 취소 (환불) — paymentId 기준
     */
    public static function cancel(string $paymentId, ?int $amount = null, string $reason = ''): array
    {
        $secret = self::secret();
        if (! $secret) return ['success' => false, 'message' => 'API Secret 미설정'];

        $payload = ['reason' => $reason ?: '가맹점 취소'];
        if ($amount !== null) $payload['amount'] = $amount;

        try {
            $res = Http::withHeaders(['Authorization' => 'PortOne ' . $secret])
                ->post(self::API_BASE . '/payments/' . rawurlencode($paymentId) . '/cancel', $payload);
            if ($res->successful()) {
                return ['success' => true, 'response' => $res->json()];
            }
            return ['success' => false, 'message' => $res->json('message') ?? '취소 실패'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
