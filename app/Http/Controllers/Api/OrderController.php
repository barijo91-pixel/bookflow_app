<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** 내 주문 목록 (역할별 필터) */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::query()->orderByDesc('id');

        if ($user->role_code === 'academy') {
            $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
            $query->whereIn('vendor_id', $vendorIds);
        } elseif ($user->role_code === 'agent') {
            $query->where('agent_user_id', $user->id);
        } elseif ($user->role_code === 'distributor') {
            $query->where('distributor_user_id', $user->id);
        } else {
            return response()->json(['ok' => false, 'error' => '권한 없음'], 403);
        }

        if ($status = $request->query('status')) {
            $query->where('status_code', $status);
        }

        $orders = $query->paginate(50);
        return response()->json([
            'ok' => true,
            'data' => collect($orders->items())->map(fn ($o) => $this->serializeListItem($o))->all(),
            'meta' => ['total' => $orders->total(), 'current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage()],
        ]);
    }

    /** 주문 상세 */
    public function show(Request $request, Order $order)
    {
        $this->authorizeAccess($request->user(), $order);
        $items = DB::table('order_items as oi')
            ->leftJoin('books as b', 'b.id', '=', 'oi.book_id')
            ->where('oi.order_id', $order->id)
            ->select('oi.*', 'b.cover_path')
            ->get()
            ->map(fn ($it) => [
                'id'             => $it->id,
                'book_id'        => $it->book_id,
                'isbn'           => $it->isbn_snapshot,
                'title'          => $it->title_snapshot,
                'qty'            => $it->qty,
                'list_price'     => $it->list_price,
                'discount_rate'  => (float) $it->discount_rate,
                'unit_price'     => $it->unit_price,
                'line_total'     => $it->line_total,
                'cover'          => $it->cover_path ? (str_starts_with($it->cover_path, 'http') ? $it->cover_path : url('storage/'.$it->cover_path)) : null,
            ]);

        return response()->json([
            'ok'   => true,
            'data' => $this->serializeListItem($order) + ['items' => $items],
        ]);
    }

    /** 주문 생성 (학원이 발행) */
    public function store(Request $request, NotificationService $notify)
    {
        $user = $request->user();
        if ($user->role_code !== 'academy') {
            return response()->json(['ok' => false, 'error' => '학원만 주문 생성 가능'], 403);
        }
        $data = $request->validate([
            'vendor_id'  => ['required', 'integer', 'exists:vendors,id'],
            'agent_user_id' => ['required', 'integer', 'exists:users,id'],
            'items'      => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'integer', 'exists:books,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'memo'       => ['nullable', 'string', 'max:1000'],
        ]);

        // 학원-vendor 접근 권한 확인
        if (! DB::table('vendor_users')->where('user_id', $user->id)->where('vendor_id', $data['vendor_id'])->exists()) {
            return response()->json(['ok' => false, 'error' => '해당 거래처에 권한이 없습니다.'], 403);
        }
        // 영업자-학원 관계 확인
        if (! DB::table('agent_vendor_discounts')
            ->where('agent_user_id', $data['agent_user_id'])
            ->where('vendor_id', $data['vendor_id'])
            ->where('is_active', true)
            ->exists()) {
            return response()->json(['ok' => false, 'error' => '해당 영업자가 이 학원과 매핑되어 있지 않습니다.'], 400);
        }

        // 영업자가 다루는 총판 중 재고가 있는 첫 번째 총판으로 자동 라우팅
        $distributorIds = DB::table('user_relations')
            ->where('child_user_id', $data['agent_user_id'])
            ->where('relation_type', 'distributor_agent')
            ->where('status', 'active')
            ->pluck('parent_user_id');

        $order = DB::transaction(function () use ($data, $distributorIds, $user) {
            $baseRate = (float) DB::table('agent_vendor_discounts')
                ->where('agent_user_id', $data['agent_user_id'])
                ->where('vendor_id', $data['vendor_id'])
                ->value('discount_rate');

            $vendor = DB::table('vendors')->find($data['vendor_id']);
            $subtotal = 0;
            $lines = [];
            $routedDistributor = null;

            foreach ($data['items'] as $item) {
                $book = Book::find($item['book_id']);
                $qty  = (int) $item['qty'];

                // 라인 할인율 결정: (영업자×학원×도서) override > (영업자×학원) base
                $rate = (float) (DB::table('agent_vendor_book_discounts')
                    ->where('agent_user_id', $data['agent_user_id'])
                    ->where('vendor_id', $data['vendor_id'])
                    ->where('book_id', $book->id)
                    ->where('is_active', true)
                    ->value('discount_rate') ?? $baseRate);
                $source = (DB::table('agent_vendor_book_discounts')
                    ->where('agent_user_id', $data['agent_user_id'])
                    ->where('vendor_id', $data['vendor_id'])
                    ->where('book_id', $book->id)
                    ->where('is_active', true)
                    ->exists()) ? 'override' : 'default';

                $unit  = (int) round($book->price * (100 - $rate) / 100);
                $total = $unit * $qty;
                $subtotal += $total;

                // 라우팅: 영업자가 다루는 총판 중 이 책 재고 있는 첫 번째
                if (! $routedDistributor) {
                    $stockDist = DB::table('book_stocks')
                        ->where('book_id', $book->id)
                        ->whereIn('distributor_user_id', $distributorIds)
                        ->where('qty', '>', 0)
                        ->orderByDesc('qty')
                        ->first();
                    if ($stockDist) $routedDistributor = $stockDist->distributor_user_id;
                }

                $lines[] = [
                    'book_id'        => $book->id,
                    'qty'            => $qty,
                    'list_price'     => $book->price,
                    'discount_rate'  => $rate,
                    'discount_source'=> $source,
                    'unit_price'     => $unit,
                    'line_total'     => $total,
                    'isbn_snapshot'  => $book->isbn,
                    'title_snapshot' => $book->title,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            // 주문번호: BFyyyyMMdd-NNNN
            $today = now()->format('Ymd');
            $todayCount = DB::table('orders')->where('order_no', 'like', "BF{$today}%")->count();
            $orderNo = 'BF'.$today.str_pad((string)($todayCount + 1), 4, '0', STR_PAD_LEFT);

            $orderId = DB::table('orders')->insertGetId([
                'order_no'              => $orderNo,
                'vendor_id'             => $data['vendor_id'],
                'agent_user_id'         => $data['agent_user_id'],
                'distributor_user_id'   => $routedDistributor,
                'status_code'           => 'requested',
                'ship_to_region_id'     => $vendor->region_id,
                'ship_to_address'       => $vendor->address,
                'ship_to_address_detail'=> $vendor->address_detail,
                'ship_to_contact'       => ($vendor->owner_name ?? '') . ' / ' . ($vendor->mobile ?? ''),
                'subtotal_amount'       => $subtotal,
                'shipping_fee'          => 0,
                'total_amount'          => $subtotal,
                'requested_at'          => now(),
                'memo'                  => $data['memo'] ?? null,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('order_items')->insert(array_merge($line, ['order_id' => $orderId]));
            }
            DB::table('order_status_logs')->insert([
                'order_id'   => $orderId,
                'to_status'  => 'requested',
                'changed_by' => $user->id,
                'created_at' => now(),
            ]);

            return Order::find($orderId);
        });

        // 알림 발송
        $agent = DB::table('users')->find($data['agent_user_id']);
        if ($agent) {
            $vendorName = DB::table('vendors')->where('id', $data['vendor_id'])->value('name');
            $notify->send('order.requested', [
                'order_no'     => $order->order_no,
                'vendor_name'  => $vendorName,
                'total_amount' => $order->total_amount,
            ], [
                ['type' => 'user', 'id' => $agent->id, 'phone' => $agent->phone, 'email' => $agent->email],
            ]);
        }

        return response()->json(['ok' => true, 'data' => $this->serializeListItem($order)], 201);
    }

    /** 영업자 확정 */
    public function confirm(Request $request, Order $order, NotificationService $notify)
    {
        $user = $request->user();
        if ($user->role_code !== 'agent' || $order->agent_user_id !== $user->id) {
            return response()->json(['ok' => false, 'error' => '권한 없음'], 403);
        }
        if ($order->status_code !== 'requested') {
            return response()->json(['ok' => false, 'error' => "현재 상태({$order->status_code})에서는 확정할 수 없습니다."], 400);
        }

        DB::transaction(function () use ($order, $user) {
            DB::table('orders')->where('id', $order->id)->update([
                'status_code'  => 'confirmed',
                'confirmed_at' => now(),
                'updated_at'   => now(),
            ]);
            DB::table('order_status_logs')->insert([
                'order_id'   => $order->id,
                'from_status'=> 'requested',
                'to_status'  => 'confirmed',
                'changed_by' => $user->id,
                'created_at' => now(),
            ]);
        });

        $this->triggerNotification($order->fresh(), 'order.confirmed', $notify);
        return response()->json(['ok' => true]);
    }

    /** 총판 접수 */
    public function accept(Request $request, Order $order, NotificationService $notify)
    {
        $user = $request->user();
        if ($user->role_code !== 'distributor') {
            return response()->json(['ok' => false, 'error' => '총판만 가능'], 403);
        }
        if ($order->status_code !== 'confirmed') {
            return response()->json(['ok' => false, 'error' => "현재 상태({$order->status_code})에서는 접수 불가"], 400);
        }
        DB::transaction(function () use ($order, $user) {
            DB::table('orders')->where('id', $order->id)->update([
                'status_code'         => 'accepted',
                'distributor_user_id' => $order->distributor_user_id ?: $user->id,
                'accepted_at'         => now(),
                'updated_at'          => now(),
            ]);
            DB::table('order_status_logs')->insert([
                'order_id'   => $order->id,
                'from_status'=> 'confirmed',
                'to_status'  => 'accepted',
                'changed_by' => $user->id,
                'created_at' => now(),
            ]);
        });
        $this->triggerNotification($order->fresh(), 'order.accepted', $notify);
        return response()->json(['ok' => true]);
    }

    // ---------- helpers ----------
    private function authorizeAccess($user, Order $order): void
    {
        $ok = match($user->role_code) {
            'agent' => $order->agent_user_id === $user->id,
            'distributor' => $order->distributor_user_id === $user->id,
            'academy' => DB::table('vendor_users')->where('user_id', $user->id)->where('vendor_id', $order->vendor_id)->exists(),
            'admin' => true,
            default => false,
        };
        if (! $ok) abort(403, '접근 권한 없음');
    }

    private function serializeListItem(Order $o): array
    {
        return [
            'id'           => $o->id,
            'order_no'     => $o->order_no,
            'status'       => $o->status_code,
            'subtotal'     => $o->subtotal_amount,
            'shipping_fee' => $o->shipping_fee,
            'total'        => $o->total_amount,
            'vendor_id'    => $o->vendor_id,
            'agent_user_id'=> $o->agent_user_id,
            'distributor_user_id' => $o->distributor_user_id,
            'requested_at' => optional($o->requested_at)->toIso8601String(),
            'confirmed_at' => optional($o->confirmed_at)->toIso8601String(),
            'shipped_at'   => optional($o->shipped_at)->toIso8601String(),
        ];
    }

    private function triggerNotification(Order $order, string $event, NotificationService $notify): void
    {
        $vendor = DB::table('vendors')->find($order->vendor_id);
        $primary = DB::table('vendor_users as vu')
            ->join('users as u', 'u.id', '=', 'vu.user_id')
            ->where('vu.vendor_id', $order->vendor_id)
            ->where('vu.is_primary', true)
            ->select('u.id','u.phone','u.email')->first();
        $agent  = DB::table('users')->find($order->agent_user_id);
        $dist   = $order->distributor_user_id ? DB::table('users')->find($order->distributor_user_id) : null;

        $context = [
            'order_no'         => $order->order_no,
            'vendor_name'      => $vendor->name ?? '',
            'agent_name'       => $agent->name ?? '',
            'distributor_name' => $dist->name ?? '',
            'total_amount'     => $order->total_amount,
        ];
        $recipients = [];
        switch ($event) {
            case 'order.confirmed':
                if ($primary) $recipients[] = ['type' => 'user', 'id' => $primary->id, 'phone' => $primary->phone, 'email' => $primary->email];
                if ($dist) $recipients[] = ['type' => 'user', 'id' => $dist->id, 'phone' => $dist->phone, 'email' => $dist->email];
                break;
            case 'order.accepted':
                if ($agent) $recipients[] = ['type' => 'user', 'id' => $agent->id, 'phone' => $agent->phone, 'email' => $agent->email];
                if ($primary) $recipients[] = ['type' => 'user', 'id' => $primary->id, 'phone' => $primary->phone, 'email' => $primary->email];
                break;
        }
        if ($recipients) $notify->send($event, $context, $recipients);
    }
}
