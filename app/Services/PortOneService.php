<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PortOne (구 아임포트) 결제 검증 서비스 — V1 REST API 기준
 *
 * 필수 site_settings:
 *  - portone_imp_uid:     가맹점 식별 코드 (imp00000000)
 *  - portone_rest_api_key: REST API Key
 *  - portone_rest_secret:  REST API Secret
 *  - portone_active:       'Y' = PG 활성화 (미설정 시 mock 결제로 fallback)
 *
 * 채널: 추후 다중 PG 지원 시 portone_channel_key 추가
 */
class PortOneService
{
    private const API_BASE = 'https://api.iamport.kr';

    /**
     * 키 설정 여부 — false면 mock 결제로 fallback
     */
    public static function isActive(): bool
    {
        return setting('portone_active') === 'Y'
            && setting('portone_imp_uid')
            && setting('portone_rest_api_key')
            && setting('portone_rest_secret');
    }

    public static function impUid(): string
    {
        return (string) setting('portone_imp_uid', '');
    }

    /**
     * Access Token 발급
     */
    public static function getAccessToken(): ?string
    {
        try {
            $res = Http::asJson()->post(self::API_BASE . '/users/getToken', [
                'imp_key'    => setting('portone_rest_api_key'),
                'imp_secret' => setting('portone_rest_secret'),
            ]);
            $body = $res->json();
            if (($body['code'] ?? -1) === 0) {
                return $body['response']['access_token'] ?? null;
            }
            Log::warning('PortOne token failed', ['body' => $body]);
        } catch (\Throwable $e) {
            Log::error('PortOne token exception', ['err' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * 결제 정보 조회 — 클라이언트가 전달한 imp_uid 기준
     * 반환: ['status' => 'paid'|'failed'|..., 'amount' => int, 'merchant_uid' => string, ...]
     */
    public static function getPayment(string $impUid): ?array
    {
        $token = self::getAccessToken();
        if (! $token) return null;

        try {
            $res = Http::withToken($token)->get(self::API_BASE . '/payments/' . $impUid);
            $body = $res->json();
            if (($body['code'] ?? -1) === 0) {
                return $body['response'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::error('PortOne getPayment exception', ['err' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * 결제 취소 (환불)
     */
    public static function cancel(string $impUid, ?int $amount = null, string $reason = ''): array
    {
        $token = self::getAccessToken();
        if (! $token) return ['success' => false, 'message' => 'PG 토큰 발급 실패'];

        $payload = ['imp_uid' => $impUid, 'reason' => $reason];
        if ($amount !== null) $payload['amount'] = $amount;

        try {
            $res = Http::withToken($token)->post(self::API_BASE . '/payments/cancel', $payload);
            $body = $res->json();
            if (($body['code'] ?? -1) === 0) {
                return ['success' => true, 'response' => $body['response']];
            }
            return ['success' => false, 'message' => $body['message'] ?? '취소 실패'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
