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

    /** 키 설정 여부 — false면 mock 결제로 fallback */
    public static function isActive(): bool
    {
        return in_array((string) setting('portone_active'), ['1', 'Y', 'true'], true)
            && setting('portone_v2_store_id')
            && setting('portone_v2_channel_key')
            && setting('portone_v2_api_secret');
    }

    public static function storeId(): string
    {
        return (string) setting('portone_v2_store_id', '');
    }

    public static function channelKey(): string
    {
        return (string) setting('portone_v2_channel_key', '');
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
