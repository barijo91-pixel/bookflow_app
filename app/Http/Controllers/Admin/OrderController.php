<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** 상태 전이 규칙 */
    private const TRANSITIONS = [
        'requested' => ['confirmed', 'canceled'],
        'confirmed' => ['accepted', 'canceled'],
        'accepted'  => ['shipped', 'canceled'],
        'shipped'   => ['in_transit', 'completed', 'returned'],
        'in_transit'=> ['completed', 'returned'],
        'completed' => ['returned'],
        'canceled'  => [],
        'returned'  => [],
    ];

    public function index(Request $request)
    {
        $status   = $request->query('status');
        $vendor   = $request->query('vendor');
        $agent    = $request->query('agent');
        $dist     = $request->query('dist');
        $q        = trim((string) $request->query('q'));
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        $query = DB::table('orders as o')
            ->leftJoin('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'o.agent_user_id')
            ->leftJoin('users as dt', 'dt.id', '=', 'o.distributor_user_id')
            ->select(
                'o.id', 'o.order_no', 'o.status_code', 'o.subtotal_amount', 'o.shipping_fee', 'o.total_amount',
                'o.requested_at', 'o.created_at',
                'v.name as vendor_name',
                'ag.name as agent_name',
                'dt.name as dist_name'
            )
            ->whereNull('o.deleted_at')
            ->orderByDesc('o.id');

        if ($status)   $query->where('o.status_code', $status);
        if ($vendor)   $query->where('o.vendor_id', $vendor);
        if ($agent)    $query->where('o.agent_user_id', $agent);
        if ($dist)     $query->where('o.distributor_user_id', $dist);
        if ($dateFrom) $query->whereDate('o.created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('o.created_at', '<=', $dateTo);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('o.order_no', 'like', "%{$q}%")
                  ->orWhere('v.name', 'like', "%{$q}%");
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        $statusOptions = DB::table('codes')->where('group_code', 'order_status')->orderBy('sort_order')->get();
        $vendors = DB::table('vendors')->orderBy('name')->get(['id', 'name']);
        $agents = DB::table('users')->where('role_code', 'agent')->orderBy('name')->get(['id', 'name']);
        $distributors = DB::table('users')->where('role_code', 'distributor')->orderBy('name')->get(['id', 'name']);

        $summary = [
            'today'    => DB::table('orders')->whereDate('created_at', today())->count(),
            'pending'  => DB::table('orders')->whereIn('status_code', ['requested', 'confirmed', 'accepted'])->count(),
            'shipping' => DB::table('orders')->whereIn('status_code', ['shipped', 'in_transit'])->count(),
            'amount_total' => (int) DB::table('orders')->whereNotIn('status_code', ['canceled', 'returned'])->sum('total_amount'),
        ];

        return view('admin.orders.index', compact(
            'orders', 'statusOptions', 'vendors', 'agents', 'distributors',
            'status', 'vendor', 'agent', 'dist', 'q', 'dateFrom', 'dateTo', 'summary'
        ));
    }

    public function show(Order $order)
    {
        $vendor = DB::table('vendors')->find($order->vendor_id);
        $agent  = DB::table('users')->find($order->agent_user_id);
        $dist   = $order->distributor_user_id ? DB::table('users')->find($order->distributor_user_id) : null;

        $items = DB::table('order_items as oi')
            ->leftJoin('books as b', 'b.id', '=', 'oi.book_id')
            ->where('oi.order_id', $order->id)
            ->select('oi.*', 'b.cover_path', 'b.isbn as book_isbn', 'b.title as book_title')
            ->get();

        $statusLogs = DB::table('order_status_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.changed_by')
            ->where('l.order_id', $order->id)
            ->orderBy('l.created_at')
            ->select('l.*', 'u.name as changed_by_name')
            ->get();

        $shipment = DB::table('order_shipments')->where('order_id', $order->id)->first();
        $courierOptions = DB::table('codes')->where('group_code', 'courier')->orderBy('sort_order')->get();

        $nextStates = self::TRANSITIONS[$order->status_code] ?? [];
        $statusLabels = DB::table('codes')->where('group_code', 'order_status')->pluck('name', 'code');

        return view('admin.orders.show', compact(
            'order', 'vendor', 'agent', 'dist', 'items', 'statusLogs', 'shipment',
            'courierOptions', 'nextStates', 'statusLabels'
        ));
    }

    /** 상태 전이 (confirm/accept/complete/return 등 단순 전이) */
    public function transition(Request $request, Order $order, NotificationService $notify)
    {
        $data = $request->validate([
            'to_status' => ['required', 'string'],
            'reason'    => ['nullable', 'string', 'max:500'],
        ]);
        $to = $data['to_status'];
        $from = $order->status_code;

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return back()->with('error', "상태 전이 불가: {$from} → {$to}");
        }

        DB::transaction(function () use ($order, $to, $from, $data) {
            $update = ['status_code' => $to, 'updated_at' => now()];
            switch ($to) {
                case 'confirmed': $update['confirmed_at'] = now(); break;
                case 'accepted':  $update['accepted_at']  = now(); break;
                case 'shipped':   $update['shipped_at']   = now(); break;
                case 'completed': $update['completed_at'] = now(); break;
                case 'canceled':  $update['canceled_at']  = now(); break;
            }
            DB::table('orders')->where('id', $order->id)->update($update);

            DB::table('order_status_logs')->insert([
                'order_id'   => $order->id,
                'from_status'=> $from,
                'to_status'  => $to,
                'changed_by' => auth()->id(),
                'reason'     => $data['reason'] ?? null,
                'created_at' => now(),
            ]);
        });

        AuditLog::log('orders', $order->id, $to, ['status_code' => $from], ['status_code' => $to, 'reason' => $data['reason'] ?? null]);

        // 알림 자동 발송
        $this->dispatchNotification($order->fresh(), $to, $notify, $data['reason'] ?? null);

        return back()->with('success', "주문 상태가 {$from} → {$to} 로 변경되었습니다.");
    }

    /** 출고 처리 (송장 입력 + 상태 변경) */
    public function ship(Request $request, Order $order, NotificationService $notify)
    {
        $data = $request->validate([
            'courier_code' => ['required', 'string', 'max:30'],
            'tracking_no'  => ['required', 'string', 'max:50'],
        ]);

        if ($order->status_code !== 'accepted') {
            return back()->with('error', "출고는 '총판접수(accepted)' 상태에서만 가능합니다. 현재: {$order->status_code}");
        }

        DB::transaction(function () use ($order, $data) {
            DB::table('order_shipments')->updateOrInsert(
                ['order_id' => $order->id],
                [
                    'courier_code'     => $data['courier_code'],
                    'tracking_no'      => $data['tracking_no'],
                    'ship_status_code' => 'shipped',
                    'shipped_at'       => now(),
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ]
            );
            DB::table('orders')->where('id', $order->id)->update([
                'status_code' => 'shipped',
                'shipped_at'  => now(),
                'updated_at'  => now(),
            ]);
            DB::table('order_status_logs')->insert([
                'order_id'   => $order->id,
                'from_status'=> 'accepted',
                'to_status'  => 'shipped',
                'changed_by' => auth()->id(),
                'reason'     => '송장입력: '.$data['courier_code'].' '.$data['tracking_no'],
                'created_at' => now(),
            ]);
        });

        AuditLog::log('orders', $order->id, 'ship',
            ['status_code' => 'accepted'],
            ['status_code' => 'shipped', 'courier_code' => $data['courier_code'], 'tracking_no' => $data['tracking_no']]
        );

        // 출고 알림 발송 (택배사명 포함)
        $courierName = DB::table('codes')->where('group_code', 'courier')->where('code', $data['courier_code'])->value('name') ?? $data['courier_code'];
        $this->dispatchNotification($order->fresh(), 'shipped', $notify, null, [
            'courier_name' => $courierName,
            'tracking_no'  => $data['tracking_no'],
        ]);

        return back()->with('success', '출고 처리 완료');
    }

    /**
     * 상태 전이 후 자동 알림 발송
     */
    private function dispatchNotification(Order $order, string $newStatus, NotificationService $notify, ?string $reason = null, array $extraContext = []): void
    {
        // 관련 사용자 + 거래처 정보
        $vendor = DB::table('vendors')->find($order->vendor_id);
        $agent  = DB::table('users')->find($order->agent_user_id);
        $dist   = $order->distributor_user_id ? DB::table('users')->find($order->distributor_user_id) : null;

        $context = array_merge([
            'order_no'         => $order->order_no,
            'vendor_name'      => $vendor->name ?? '',
            'agent_name'       => $agent->name ?? '',
            'distributor_name' => $dist->name ?? '',
            'total_amount'     => $order->total_amount,
            'reason'           => $reason ?? '',
        ], $extraContext);

        $eventMap = [
            'confirmed' => 'order.confirmed',
            'accepted'  => 'order.accepted',
            'shipped'   => 'order.shipped',
            'canceled'  => 'order.canceled',
        ];
        $event = $eventMap[$newStatus] ?? null;
        if (! $event) return;

        // 수신자 결정
        $recipients = [];
        $vendorPhone = $vendor->mobile ?? null;
        $vendorEmail = null;
        // 학원 주담당자 이메일
        $primaryUser = DB::table('vendor_users as vu')
            ->join('users as u', 'u.id', '=', 'vu.user_id')
            ->where('vu.vendor_id', $order->vendor_id)
            ->where('vu.is_primary', true)
            ->select('u.id','u.phone','u.email')
            ->first();
        if ($primaryUser) { $vendorPhone = $primaryUser->phone; $vendorEmail = $primaryUser->email; }

        switch ($event) {
            case 'order.confirmed':
                // 학원 + 총판에게 알림
                if ($vendorPhone) $recipients[] = ['type' => 'vendor', 'id' => $order->vendor_id, 'phone' => $vendorPhone, 'email' => $vendorEmail];
                if ($dist && $dist->phone) $recipients[] = ['type' => 'user', 'id' => $dist->id, 'phone' => $dist->phone, 'email' => $dist->email];
                break;
            case 'order.accepted':
                if ($agent && $agent->phone) $recipients[] = ['type' => 'user', 'id' => $agent->id, 'phone' => $agent->phone, 'email' => $agent->email];
                if ($vendorPhone) $recipients[] = ['type' => 'vendor', 'id' => $order->vendor_id, 'phone' => $vendorPhone, 'email' => $vendorEmail];
                break;
            case 'order.shipped':
                if ($vendorPhone) $recipients[] = ['type' => 'vendor', 'id' => $order->vendor_id, 'phone' => $vendorPhone, 'email' => $vendorEmail];
                break;
            case 'order.canceled':
                if ($vendorPhone) $recipients[] = ['type' => 'vendor', 'id' => $order->vendor_id, 'phone' => $vendorPhone, 'email' => $vendorEmail];
                if ($agent && $agent->phone) $recipients[] = ['type' => 'user', 'id' => $agent->id, 'phone' => $agent->phone, 'email' => $agent->email];
                if ($dist && $dist->phone) $recipients[] = ['type' => 'user', 'id' => $dist->id, 'phone' => $dist->phone, 'email' => $dist->email];
                break;
        }

        if ($recipients) {
            $notify->send($event, $context, $recipients);
        }
    }
}
