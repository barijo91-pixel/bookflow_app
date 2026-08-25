<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * 주문 확정 워크플로 — 결제/여신 규칙의 단일 출처.
 *
 * 규칙 (2026-08-25 형아 결정):
 *  - 결제 학원: 주문 대금이 전액 결제되면 requested → confirmed **자동 확정**.
 *    영업자가 손댈 일 없음. 결제가 곧 확정 신호.
 *  - 여신(외상) 학원(vendors.credit_allowed): 결제가 없으니 **영업자가 수동 확정**.
 *  - 그 뒤 총판이 출고확정(별도).
 */
class OrderWorkflowService
{
    /** 이 주문에 들어온 결제 완료액 합계 */
    public static function paidAmount(int $orderId): int
    {
        return (int) DB::table('payment_requests')
            ->where('order_id', $orderId)
            ->where('status', 'paid')
            ->sum('amount');
    }

    /** 주문 대금이 전액 결제됐는가 */
    public static function isFullyPaid(object $order): bool
    {
        $total = (int) $order->total_amount;
        return $total > 0 && self::paidAmount($order->id) >= $total;
    }

    /** 이 학원이 여신(외상) 학원인가 */
    public static function isCreditVendor(int $vendorId): bool
    {
        return (bool) DB::table('vendors')->where('id', $vendorId)->value('credit_allowed');
    }

    /**
     * 결제 완료로 자동 확정 — 결제 성공 지점에서 호출.
     * requested 이고 전액 결제됐을 때만 confirmed 로. 반환: 확정했으면 true.
     */
    public static function autoConfirmIfPaid(int $orderId, ?NotificationService $notify = null): bool
    {
        $order = DB::table('orders')->where('id', $orderId)->whereNull('deleted_at')->first();
        if (! $order || $order->status_code !== 'requested') return false;
        if (! self::isFullyPaid($order)) return false;

        return self::transitionToConfirmed($order, null, '결제 완료 자동 확정', ['auto_paid' => true], $notify);
    }

    /**
     * 영업자 수동 확정 — 여신 학원의 미결제 주문만 대상.
     * 반환: [ok=bool, message=string]
     */
    public static function confirmByAgent(object $order, int $agentUserId, ?NotificationService $notify = null): array
    {
        if ($order->status_code !== 'requested') {
            return ['ok' => false, 'message' => '접수 상태의 주문만 확정할 수 있습니다.'];
        }
        // 결제된 주문은 자동 확정 대상 — 수동 확정 불필요/불가
        if (self::isFullyPaid($order)) {
            // 혹시 자동확정이 안 걸렸으면 여기서라도 확정
            self::transitionToConfirmed($order, $agentUserId, '결제 완료 확정', ['auto_paid' => true], $notify);
            return ['ok' => true, 'message' => '결제 완료로 확정되었습니다.'];
        }
        // 미결제 주문은 여신 학원만 확정 가능
        if (! self::isCreditVendor($order->vendor_id)) {
            return ['ok' => false, 'message' => '결제가 완료되지 않은 주문입니다. (여신 학원만 미결제 확정 가능)'];
        }
        $ok = self::transitionToConfirmed($order, $agentUserId, '여신 주문 수동 확정', ['credit' => true], $notify);
        return ['ok' => $ok, 'message' => $ok ? '여신 주문을 확정했습니다.' : '확정에 실패했습니다.'];
    }

    /**
     * requested → confirmed 원자적 전환 + 로그 + 알림.
     * where status='requested' 로 잠가 동시 확정을 한 명만 통과시킨다.
     */
    private static function transitionToConfirmed(object $order, ?int $byUserId, string $reason, array $auditExtra, ?NotificationService $notify): bool
    {
        $claimed = DB::transaction(function () use ($order, $byUserId, $reason) {
            $updated = DB::table('orders')->where('id', $order->id)->where('status_code', 'requested')
                ->update(['status_code' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now()]);
            if ($updated) {
                DB::table('order_status_logs')->insert([
                    'order_id'    => $order->id,
                    'from_status' => 'requested',
                    'to_status'   => 'confirmed',
                    'changed_by'  => $byUserId,
                    'reason'      => $reason,
                    'created_at'  => now(),
                ]);
            }
            return $updated;
        });
        if (! $claimed) return false;

        AuditLog::log('orders', $order->id, 'confirmed',
            ['status_code' => 'requested'],
            array_merge(['status_code' => 'confirmed'], $auditExtra));

        // 학원(vendor)에게 확정 알림
        try {
            $notify = $notify ?: app(NotificationService::class);
            $vendor = DB::table('vendors')->find($order->vendor_id);
            if ($vendor) {
                $notify->send('order.confirmed', [
                    'order_no'     => $order->order_no,
                    'vendor_name'  => $vendor->name ?? '',
                    'total_amount' => $order->total_amount,
                ], [
                    ['type' => 'vendor', 'id' => $vendor->id, 'phone' => $vendor->mobile ?? null, 'email' => null],
                ]);
            }
        } catch (\Throwable $e) {
            // 알림 실패는 확정 자체에 영향 없음
        }

        return true;
    }
}
