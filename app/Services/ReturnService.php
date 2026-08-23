<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * 반품 처리 — 접수 → 확정(환불) / 반려 / 취소.
 *
 * 반품 단위는 품목별 수량("이 교재 3권만").
 * 확정 시 그 주문의 실PG 결제 건에서 반품액만큼 **부분취소**를 자동 호출한다.
 *  - 결제가 여러 건(소매 학부모별)이면 남은 환불 여지가 있는 건부터 차례로 나눠 취소
 *  - mock 결제·PG 미사용 주문은 취소 없이 장부 기록만 (refund_status=none)
 *  - 부분취소 실패는 반품 확정을 막지 않는다 — failed 로 남겨 재시도 (이중취소는
 *    return_refunds 유니크 + refunded_amount 누계로 방어)
 */
class ReturnService
{
    /** 접수 가능한 주문 상태 — 물건이 나갔거나 나가는 중일 때 */
    public const RETURNABLE_ORDER_STATUS = ['accepted', 'shipped', 'in_transit', 'completed'];

    public const REASONS = [
        'damaged'      => '파손·불량',
        'wrong_book'   => '오배송',
        'over_order'   => '과다 주문',
        'student_left' => '학생 이탈',
        'other'        => '기타',
    ];

    public const STATUS = [
        'requested' => '접수',
        'confirmed' => '확정',
        'rejected'  => '반려',
        'canceled'  => '취소',
    ];

    /**
     * 주문 품목별 반품 가능 수량.
     * 반환: [order_item_id => ['qty'=>주문수량, 'returned'=>이미 반품(접수+확정), 'left'=>남은 수량]]
     */
    public static function returnableQty(int $orderId): array
    {
        $items = DB::table('order_items')->where('order_id', $orderId)
            ->get(['id', 'qty'])->keyBy('id');

        $used = DB::table('return_items as ri')
            ->join('returns as r', 'r.id', '=', 'ri.return_id')
            ->where('r.order_id', $orderId)
            ->whereIn('r.status', ['requested', 'confirmed'])
            ->whereNull('r.deleted_at')
            ->selectRaw('ri.order_item_id, SUM(ri.qty) as used')
            ->groupBy('ri.order_item_id')->pluck('used', 'order_item_id');

        $out = [];
        foreach ($items as $id => $it) {
            $u = (int) ($used[$id] ?? 0);
            $out[$id] = ['qty' => (int) $it->qty, 'returned' => $u, 'left' => max(0, (int) $it->qty - $u)];
        }
        return $out;
    }

    /**
     * 반품 접수.
     * @param array $lines [order_item_id => qty] (0 은 무시)
     * @param int|null $paymentRequestId 환불 대상 학부모 결제 (소매 — 지정하면 그 결제에서 우선 환불)
     * @return object returns 행
     */
    public static function create(object $order, array $lines, string $reasonCode, ?string $reasonText, int $byUserId, ?int $paymentRequestId = null): object
    {
        // 대상 결제 지정 시 — 이 주문의 결제 완료 건이어야 한다
        if ($paymentRequestId) {
            $ok = DB::table('payment_requests')->where('id', $paymentRequestId)
                ->where('order_id', $order->id)->where('status', 'paid')->exists();
            if (! $ok) {
                throw new \RuntimeException('환불 대상 결제가 이 주문의 결제 완료 건이 아닙니다.');
            }
        }

        $left  = self::returnableQty($order->id);
        $items = DB::table('order_items')->where('order_id', $order->id)->get()->keyBy('id');

        $rows = []; $totalQty = 0; $totalAmount = 0;
        foreach ($lines as $itemId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) continue;
            $it = $items->get((int) $itemId);
            if (! $it) {
                throw new \RuntimeException('주문에 없는 품목입니다.');
            }
            if ($qty > ($left[$it->id]['left'] ?? 0)) {
                throw new \RuntimeException("'{$it->title_snapshot}' 반품 가능 수량({$left[$it->id]['left']}권)을 초과했습니다.");
            }
            $rows[] = [
                'order_item_id'  => $it->id,
                'book_id'        => $it->book_id,
                'isbn_snapshot'  => $it->isbn_snapshot,
                'title_snapshot' => $it->title_snapshot,
                'qty'            => $qty,
                'unit_price'     => (int) $it->unit_price,
                'line_total'     => (int) $it->unit_price * $qty,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
            $totalQty    += $qty;
            $totalAmount += (int) $it->unit_price * $qty;
        }
        if (! $rows) {
            throw new \RuntimeException('반품할 수량을 1권 이상 입력해 주세요.');
        }

        return DB::transaction(function () use ($order, $rows, $reasonCode, $reasonText, $byUserId, $totalQty, $totalAmount, $paymentRequestId) {
            $today = now()->format('Ymd');
            $seq   = DB::table('returns')->where('return_no', 'like', "RT{$today}%")->lockForUpdate()->count() + 1;

            $returnId = DB::table('returns')->insertGetId([
                'return_no'           => 'RT' . $today . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'order_id'            => $order->id,
                'vendor_id'           => $order->vendor_id,
                'payment_request_id'  => $paymentRequestId,
                'agent_user_id'       => $order->agent_user_id,
                'distributor_user_id' => $order->distributor_user_id,
                'status'              => 'requested',
                'reason_code'         => $reasonCode,
                'reason_text'         => $reasonText,
                'total_qty'           => $totalQty,
                'total_amount'        => $totalAmount,
                'requested_by'        => $byUserId,
                'requested_at'        => now(),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            foreach ($rows as &$row) $row['return_id'] = $returnId;
            unset($row);
            DB::table('return_items')->insert($rows);

            AuditLog::log('returns', $returnId, 'created', null, [
                'order_id' => $order->id, 'qty' => $totalQty, 'amount' => $totalAmount,
            ]);

            return DB::table('returns')->find($returnId);
        });
    }

    /**
     * 반품 확정 + PG 부분취소.
     * 반환: ['refund_status' => ..., 'refund_amount' => int, 'errors' => string[]]
     */
    public static function confirm(object $return, int $byUserId): array
    {
        if ($return->status !== 'requested') {
            throw new \RuntimeException('접수 상태의 반품만 확정할 수 있습니다.');
        }

        // 1) 확정으로 전환 (환불과 분리 — 부분취소가 실패해도 반품 자체는 확정 유지)
        DB::table('returns')->where('id', $return->id)->update([
            'status'       => 'confirmed',
            'confirmed_by' => $byUserId,
            'confirmed_at' => now(),
            'updated_at'   => now(),
        ]);

        return self::refund($return->id);
    }

    /**
     * 환불(부분취소) 실행 — 확정된 반품의 미환불 잔액을 PG 에서 취소한다.
     * 재시도에도 그대로 쓰인다 (남은 금액만 다시 시도).
     */
    public static function refund(int $returnId): array
    {
        $return = DB::table('returns')->find($returnId);
        if (! $return || $return->status !== 'confirmed') {
            throw new \RuntimeException('확정된 반품만 환불할 수 있습니다.');
        }

        $need = (int) $return->total_amount - (int) $return->refund_amount;
        if ($need <= 0) {
            return ['refund_status' => $return->refund_status, 'refund_amount' => 0, 'errors' => []];
        }

        // 환불 대상 — 실PG 결제만 (mock 제외).
        // 대상 결제가 지정된 반품은 그 결제부터 취소하고, 모자랄 때만 다른 건으로 넘어간다.
        $payments = DB::table('payment_requests')
            ->where('order_id', $return->order_id)
            ->where('status', 'paid')
            ->whereNotNull('pg_payment_id')
            ->where('pg_payment_id', 'not like', 'MOCK-%')
            ->orderByRaw('(id = ?) desc', [(int) ($return->payment_request_id ?? 0)])
            ->orderBy('id')
            ->get();

        if ($payments->isEmpty()) {
            DB::table('returns')->where('id', $return->id)
                ->update(['refund_status' => 'none', 'updated_at' => now()]);
            AuditLog::log('returns', $return->id, 'refund', null,
                ['refund' => 'none', 'amount' => (int) $return->total_amount]);
            return ['refund_status' => 'none', 'refund_amount' => 0, 'errors' => []];
        }

        $refunded = 0; $errors = [];

        foreach ($payments as $pay) {
            if ($need <= 0) break;
            $room = (int) $pay->amount - (int) $pay->refunded_amount;   // 이 결제에서 더 취소 가능한 금액
            if ($room <= 0) continue;
            $cut = min($need, $room);

            // 이중 취소 방지 — 같은 반품·같은 결제에 성공 기록이 있으면 건너뜀
            $prev = DB::table('return_refunds')
                ->where('return_id', $return->id)->where('payment_request_id', $pay->id)->first();
            if ($prev && $prev->status === 'success') continue;

            $res = PortOneService::cancel(
                $pay->pg_payment_id, $cut,
                '반품 환불 (' . $return->return_no . ')'
            );

            $row = [
                'return_id'          => $return->id,
                'payment_request_id' => $pay->id,
                'pg_payment_id'      => $pay->pg_payment_id,
                'amount'             => $cut,
                'status'             => $res['success'] ? 'success' : 'failed',
                'error_message'      => $res['success'] ? null : mb_substr((string) ($res['message'] ?? ''), 0, 500),
                'response_json'      => isset($res['response']) ? json_encode($res['response'], JSON_UNESCAPED_UNICODE) : null,
                'updated_at'         => now(),
            ];
            if ($prev) {
                DB::table('return_refunds')->where('id', $prev->id)->update($row);
            } else {
                $row['created_at'] = now();
                DB::table('return_refunds')->insert($row);
            }

            if ($res['success']) {
                DB::table('payment_requests')->where('id', $pay->id)
                    ->increment('refunded_amount', $cut);
                $refunded += $cut;
                $need     -= $cut;
            } else {
                $errors[] = ($pay->parent_name ?: '결제 #' . $pay->id) . ': ' . ($res['message'] ?? '취소 실패');
            }
        }

        $totalRefunded = (int) $return->refund_amount + $refunded;
        $status = $totalRefunded >= (int) $return->total_amount ? 'done'
                : ($totalRefunded > 0 ? 'partial' : 'failed');

        DB::table('returns')->where('id', $return->id)->update([
            'refund_status' => $status,
            'refund_amount' => $totalRefunded,
            'refund_error'  => $errors ? mb_substr(implode(' / ', $errors), 0, 500) : null,
            'updated_at'    => now(),
        ]);

        AuditLog::log('returns', $return->id, 'refund', null, [
            'refund' => $status, 'refunded' => $refunded, 'total' => $totalRefunded,
        ]);

        return ['refund_status' => $status, 'refund_amount' => $refunded, 'errors' => $errors];
    }

    public static function reject(object $return, int $byUserId, ?string $reason): void
    {
        if ($return->status !== 'requested') {
            throw new \RuntimeException('접수 상태의 반품만 반려할 수 있습니다.');
        }
        DB::table('returns')->where('id', $return->id)->update([
            'status'     => 'rejected',
            'memo'       => trim(($return->memo ? $return->memo . "\n" : '') . '[반려] ' . ($reason ?: '')),
            'updated_at' => now(),
        ]);
        AuditLog::log('returns', $return->id, 'rejected', null, ['reason' => $reason]);
    }

    public static function cancel(object $return, int $byUserId): void
    {
        if ($return->status !== 'requested') {
            throw new \RuntimeException('접수 상태의 반품만 취소할 수 있습니다.');
        }
        DB::table('returns')->where('id', $return->id)->update([
            'status' => 'canceled', 'updated_at' => now(),
        ]);
        AuditLog::log('returns', $return->id, 'canceled', null, null);
    }
}
