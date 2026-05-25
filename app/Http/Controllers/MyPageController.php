<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MyPageController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // 역할별 상황 + 최근 주문
        $context = ['user' => $user];

        // 시·도/시·군·구 표시명
        $regionName = null;
        if ($user->region_id) {
            $reg = DB::table('regions as r')
                ->leftJoin('regions as p', 'p.id', '=', 'r.parent_id')
                ->where('r.id', $user->region_id)
                ->select('r.name as name', 'p.name as parent_name')
                ->first();
            if ($reg) $regionName = trim(($reg->parent_name ?? '').' '.$reg->name);
        }
        $context['region_name'] = $regionName;

        // 역할별 위젯
        switch ($user->role_code) {
            case 'agent':
                $vendorIds = DB::table('agent_vendor_discounts')
                    ->where('agent_user_id', $user->id)->where('is_active', true)
                    ->pluck('vendor_id');
                $context['my_vendors'] = DB::table('vendors')
                    ->whereIn('id', $vendorIds)
                    ->select('id', 'name', 'mobile', 'status_code')
                    ->orderBy('name')->limit(10)->get();
                $context['recent_orders'] = $this->recentOrders($user);
                $context['my_distributors'] = DB::table('user_relations as r')
                    ->join('users as u', 'u.id', '=', 'r.parent_user_id')
                    ->where('r.child_user_id', $user->id)
                    ->where('r.relation_type', 'distributor_agent')
                    ->where('r.status', 'active')
                    ->select('u.id', 'u.name')->get();
                break;

            case 'academy':
                $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
                $context['my_academies'] = DB::table('vendors')
                    ->whereIn('id', $vendorIds)
                    ->select('id', 'name', 'mobile', 'status_code')
                    ->orderBy('name')->get();
                $context['my_agents'] = DB::table('agent_vendor_discounts as avd')
                    ->join('users as u', 'u.id', '=', 'avd.agent_user_id')
                    ->whereIn('avd.vendor_id', $vendorIds)
                    ->where('avd.is_active', true)
                    ->select('u.id', 'u.name', 'u.phone', 'avd.discount_rate')
                    ->orderBy('u.name')->get();
                $context['recent_orders'] = $this->recentOrders($user);
                break;

            case 'distributor':
                $context['my_agents'] = DB::table('user_relations as r')
                    ->join('users as u', 'u.id', '=', 'r.child_user_id')
                    ->where('r.parent_user_id', $user->id)
                    ->where('r.relation_type', 'distributor_agent')
                    ->where('r.status', 'active')
                    ->select('u.id', 'u.name', 'u.email')
                    ->orderBy('u.name')->limit(20)->get();
                $context['recent_orders'] = $this->recentOrders($user);
                $context['stock_summary'] = [
                    'total_books' => DB::table('book_stocks')->where('distributor_user_id', $user->id)->count(),
                    'total_qty'   => (int) DB::table('book_stocks')->where('distributor_user_id', $user->id)->sum('qty'),
                    'low_stock'   => DB::table('book_stocks')
                        ->where('distributor_user_id', $user->id)
                        ->whereColumn('qty', '<=', 'low_stock_threshold')->count(),
                ];
                break;
        }

        return view('public.mypage.index', $context);
    }

    private function recentOrders(User $user)
    {
        $query = DB::table('orders as o')
            ->leftJoin('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'o.agent_user_id')
            ->select('o.id', 'o.order_no', 'o.status_code', 'o.total_amount', 'o.requested_at',
                'v.name as vendor_name', 'ag.name as agent_name')
            ->whereNull('o.deleted_at')
            ->orderByDesc('o.id')->limit(10);

        switch ($user->role_code) {
            case 'agent':
                $query->where('o.agent_user_id', $user->id); break;
            case 'distributor':
                $query->where('o.distributor_user_id', $user->id); break;
            case 'academy':
                $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
                $query->whereIn('o.vendor_id', $vendorIds); break;
        }
        return $query->get();
    }

    public function showProfile()
    {
        return view('public.mypage.profile', ['user' => Auth::user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);
        $user->update($data);
        return back()->with('success', '정보가 저장되었습니다.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'max:50'],
        ], [
            'password.min'     => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.letters' => '비밀번호에 영문자가 1자 이상 포함되어야 합니다.',
            'password.numbers' => '비밀번호에 숫자가 1자 이상 포함되어야 합니다.',
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => '현재 비밀번호가 일치하지 않습니다.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => '새 비밀번호는 기존 비밀번호와 달라야 합니다.']);
        }
        $user->password = $data['password'];
        $user->password_change_required = false; // 변경 완료 → 강제 플래그 해제
        $user->save();
        AuditLog::log('users', $user->id, 'change_password', null, null);
        return back()->with('success', '비밀번호가 변경되었습니다.');
    }

    // -------------------- 주문 상세 + 상태 전환 (Phase B-3) --------------------
    /** 권한: 본인이 관련된 주문인지 검증 */
    private function authorizeOrder($order): \App\Models\User
    {
        $user = Auth::user();
        $isAgent      = $user->role_code === 'agent' && $order->agent_user_id == $user->id;
        $isDist       = $user->role_code === 'distributor' && $order->distributor_user_id == $user->id;
        $isAcademy    = false;
        if ($user->role_code === 'academy') {
            $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
            $isAcademy = $vendorIds->contains($order->vendor_id);
        }
        if (! ($isAgent || $isDist || $isAcademy)) {
            abort(403, '본인 주문이 아닙니다.');
        }
        return $user;
    }

    /** 주문 상세 페이지 (역할별 가능한 액션 표시) */
    public function showOrder($id)
    {
        $order = \App\Models\Order::findOrFail($id);
        $user = $this->authorizeOrder($order);

        $vendor = DB::table('vendors')->find($order->vendor_id);
        $agent  = $order->agent_user_id ? DB::table('users')->find($order->agent_user_id) : null;
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

        // 역할별 가능한 액션
        $canConfirm = ($user->role_code === 'agent' && $order->agent_user_id == $user->id && $order->status_code === 'requested');
        $canAccept  = ($user->role_code === 'distributor' && $order->distributor_user_id == $user->id && $order->status_code === 'confirmed');
        $canShip    = ($user->role_code === 'distributor' && $order->distributor_user_id == $user->id && $order->status_code === 'accepted');
        $canCancel  = in_array($order->status_code, ['requested','confirmed','accepted'])
                      && ($user->role_code === 'agent' && $order->agent_user_id == $user->id
                       || $user->role_code === 'distributor' && $order->distributor_user_id == $user->id);

        return view('public.mypage.order_show', compact(
            'user', 'order', 'vendor', 'agent', 'dist', 'items', 'statusLogs', 'shipment',
            'courierOptions', 'canConfirm', 'canAccept', 'canShip', 'canCancel'
        ));
    }

    /** 상태 전이 (영업자 confirm, 총판 accept, 취소 등) */
    public function transitionOrder(Request $request, $id, \App\Services\NotificationService $notify)
    {
        $order = \App\Models\Order::findOrFail($id);
        $user  = $this->authorizeOrder($order);

        $data = $request->validate([
            'to_status' => ['required', 'in:confirmed,accepted,canceled'],
            'reason'    => ['nullable', 'string', 'max:500'],
        ]);
        $to = $data['to_status'];
        $from = $order->status_code;

        // 역할별 허용 전이 확인
        $allowed = false;
        if ($to === 'confirmed' && $user->role_code === 'agent' && $from === 'requested' && $order->agent_user_id == $user->id) $allowed = true;
        if ($to === 'accepted'  && $user->role_code === 'distributor' && $from === 'confirmed' && $order->distributor_user_id == $user->id) $allowed = true;
        if ($to === 'canceled'  && in_array($from, ['requested','confirmed','accepted'])) {
            if ($user->role_code === 'agent' && $order->agent_user_id == $user->id) $allowed = true;
            if ($user->role_code === 'distributor' && $order->distributor_user_id == $user->id) $allowed = true;
        }
        if (! $allowed) {
            return back()->with('error', "상태 전이 불가 ({$from} → {$to})");
        }

        DB::transaction(function () use ($order, $to, $from, $data) {
            $update = ['status_code' => $to, 'updated_at' => now()];
            switch ($to) {
                case 'confirmed': $update['confirmed_at'] = now(); break;
                case 'accepted':  $update['accepted_at']  = now(); break;
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

        AuditLog::log('orders', $order->id, $to,
            ['status_code' => $from],
            ['status_code' => $to, 'reason' => $data['reason'] ?? null]);

        $this->dispatchOrderNotification($order->fresh(), $to, $notify, $data['reason'] ?? null);

        $msg = match($to) {
            'confirmed' => '주문을 확정했습니다.',
            'accepted'  => '주문을 접수했습니다.',
            'canceled'  => '주문을 취소했습니다.',
            default     => '상태가 변경되었습니다.',
        };
        return back()->with('success', $msg);
    }

    /** 출고 처리 (총판 전용, 송장 입력) */
    public function shipOrder(Request $request, $id, \App\Services\NotificationService $notify)
    {
        $order = \App\Models\Order::findOrFail($id);
        $user  = $this->authorizeOrder($order);

        if ($user->role_code !== 'distributor' || $order->distributor_user_id != $user->id) {
            abort(403, '총판만 출고 처리할 수 있습니다.');
        }
        if ($order->status_code !== 'accepted') {
            return back()->with('error', "출고는 '총판접수' 상태에서만 가능합니다. 현재: {$order->status_code}");
        }

        $data = $request->validate([
            'courier_code' => ['required', 'string', 'max:30'],
            'tracking_no'  => ['required', 'string', 'max:50'],
        ]);

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
            ['status_code' => 'shipped', 'courier_code' => $data['courier_code'], 'tracking_no' => $data['tracking_no']]);

        $courierName = DB::table('codes')->where('group_code', 'courier')->where('code', $data['courier_code'])->value('name') ?? $data['courier_code'];
        $this->dispatchOrderNotification($order->fresh(), 'shipped', $notify, null, [
            'courier_name' => $courierName,
            'tracking_no'  => $data['tracking_no'],
        ]);

        return back()->with('success', '출고 처리 완료');
    }

    /** 주문 상태 변경 알림 발송 (Admin\OrderController와 동일 로직) */
    private function dispatchOrderNotification(\App\Models\Order $order, string $newStatus, \App\Services\NotificationService $notify, ?string $reason = null, array $extraContext = []): void
    {
        $vendor = DB::table('vendors')->find($order->vendor_id);
        $agent  = $order->agent_user_id ? DB::table('users')->find($order->agent_user_id) : null;
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

        $recipients = [];
        if ($agent && $newStatus !== 'confirmed')   $recipients[] = ['type' => 'user', 'id' => $agent->id, 'phone' => $agent->phone, 'email' => $agent->email];
        if ($dist && in_array($newStatus, ['accepted', 'shipped'])) $recipients[] = ['type' => 'user', 'id' => $dist->id, 'phone' => $dist->phone, 'email' => $dist->email];
        if ($vendor && in_array($newStatus, ['confirmed', 'accepted', 'shipped', 'canceled'])) {
            $recipients[] = ['type' => 'vendor', 'id' => $vendor->id, 'phone' => $vendor->mobile ?? null, 'email' => null];
        }

        $notify->send($event, $context, $recipients);
    }

    // -------------------- 역할별 메뉴 (Phase A: placeholder) --------------------
    /** 받은 주문 (총판) / 주문 확인 (영업자) / 주문 내역 (학원) - 통합 라우트 */
    public function ordersIndex(Request $request)
    {
        $user = Auth::user();
        $status = $request->query('status'); // 필터링용

        $query = DB::table('orders as o')
            ->leftJoin('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'o.agent_user_id')
            ->leftJoin('users as ds', 'ds.id', '=', 'o.distributor_user_id')
            ->whereNull('o.deleted_at')
            ->select(
                'o.id', 'o.order_no', 'o.status_code', 'o.total_amount',
                'o.requested_at', 'o.confirmed_at', 'o.accepted_at',
                'o.shipped_at', 'o.completed_at', 'o.created_at',
                'v.name as vendor_name',
                'ag.name as agent_name', 'ag.login_id as agent_login_id',
                'ds.name as distributor_name'
            );

        // 역할별 필터 (자기 데이터만)
        switch ($user->role_code) {
            case 'agent':
                $query->where('o.agent_user_id', $user->id);
                $title = '주문 확인';
                break;
            case 'distributor':
                $query->where('o.distributor_user_id', $user->id);
                $title = '받은 주문';
                break;
            case 'academy':
                $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
                $query->whereIn('o.vendor_id', $vendorIds);
                $title = '주문 내역';
                break;
            default:
                abort(403);
        }

        if ($status) {
            $query->where('o.status_code', $status);
        }

        $orders = $query->orderByDesc('o.id')->paginate(20)->withQueryString();

        // 상태별 카운트 (필터 UI용)
        $statusBaseQuery = DB::table('orders')->whereNull('deleted_at');
        switch ($user->role_code) {
            case 'agent':       $statusBaseQuery->where('agent_user_id', $user->id); break;
            case 'distributor': $statusBaseQuery->where('distributor_user_id', $user->id); break;
            case 'academy':     $statusBaseQuery->whereIn('vendor_id', DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id')); break;
        }
        $statusCounts = $statusBaseQuery->select('status_code', DB::raw('count(*) as cnt'))
            ->groupBy('status_code')->pluck('cnt', 'status_code');

        return view('public.mypage.orders', [
            'user'   => $user,
            'orders' => $orders,
            'title'  => $title,
            'status' => $status,
            'statusCounts' => $statusCounts,
        ]);
    }

    /** 재고 관리 (총판) */
    public function stocksIndex()
    {
        return view('public.mypage.placeholder', [
            'user'  => Auth::user(),
            'title' => '재고 관리',
            'icon'  => 'bi-box-seam',
            'description' => '보유 도서별 재고를 조정하는 페이지입니다. 곧 제공됩니다.',
        ]);
    }

    /** 소속 영업자 (총판) */
    public function agentsIndex()
    {
        return view('public.mypage.placeholder', [
            'user'  => Auth::user(),
            'title' => '소속 영업자',
            'icon'  => 'bi-person-badge',
            'description' => '총판 산하 영업자 목록 및 매핑 관리. 곧 제공됩니다.',
        ]);
    }

    /** 담당 학원 (영업자) */
    public function vendorsIndex()
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') {
            abort(403, '영업자만 접근 가능합니다.');
        }

        $vendors = DB::table('agent_vendor_discounts as avd')
            ->join('vendors as v', 'v.id', '=', 'avd.vendor_id')
            ->leftJoin('regions as r', 'r.id', '=', 'v.region_id')
            ->leftJoin('regions as p', 'p.id', '=', 'r.parent_id')
            ->where('avd.agent_user_id', $user->id)
            ->select(
                'v.id', 'v.name', 'v.owner_name', 'v.business_no',
                'v.mobile', 'v.tel', 'v.status_code',
                'avd.discount_rate', 'avd.is_active as discount_active',
                'avd.started_at', 'avd.ended_at',
                'r.name as sigungu_name', 'p.name as sido_name'
            )
            ->orderByDesc('avd.is_active')
            ->orderBy('v.name')
            ->get();

        return view('public.mypage.vendors', [
            'user' => $user,
            'vendors' => $vendors,
        ]);
    }

    /** 할인율 관리 (영업자) */
    public function discountsIndex()
    {
        return view('public.mypage.placeholder', [
            'user'  => Auth::user(),
            'title' => '할인율 관리',
            'icon'  => 'bi-percent',
            'description' => '학원별·도서별 할인율 조정. 곧 제공됩니다.',
        ]);
    }

    /** 도서 주문하기 (학원) - 검색 + 장바구니 + 주문 생성 */
    public function orderNew(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') {
            abort(403, '학원만 주문할 수 있습니다.');
        }

        // 1. 학원의 vendor 찾기 (첫 번째 매핑)
        $vendorId = DB::table('vendor_users')->where('user_id', $user->id)->value('vendor_id');
        $vendor = $vendorId ? DB::table('vendors')->find($vendorId) : null;

        // 2. 영업자 매핑 (이 vendor에 매핑된 active agent들, 첫 번째 자동 선택)
        $agents = collect();
        $selectedAgent = null;
        if ($vendor) {
            $agents = DB::table('agent_vendor_discounts as avd')
                ->join('users as u', 'u.id', '=', 'avd.agent_user_id')
                ->where('avd.vendor_id', $vendorId)
                ->where('avd.is_active', true)
                ->select('u.id', 'u.name', 'u.login_id', 'avd.discount_rate as general_rate')
                ->get();
            $selectedAgentId = $request->query('agent_id', $agents->first()->id ?? null);
            $selectedAgent = $agents->firstWhere('id', $selectedAgentId);
        }

        // 3. 도서별 할인율 매핑 (선택된 영업자 기준)
        $bookDiscounts = collect();
        if ($selectedAgent) {
            $bookDiscounts = DB::table('agent_vendor_book_discounts')
                ->where('agent_user_id', $selectedAgent->id)
                ->where('vendor_id', $vendorId)
                ->where('is_active', true)
                ->pluck('discount_rate', 'book_id');
        }

        // 4. 도서 검색 + 목록
        $q = trim((string) $request->query('q'));
        $booksQuery = DB::table('books')
            ->whereNull('deleted_at')
            ->where('status_code', 'selling')
            ->leftJoin('publishers as p', 'p.id', '=', 'books.publisher_id')
            ->select('books.*', 'p.name as publisher_name');
        if ($q !== '') {
            $booksQuery->where(function ($w) use ($q) {
                $w->where('books.title', 'like', "%{$q}%")
                  ->orWhere('books.isbn', 'like', "%{$q}%")
                  ->orWhere('books.series_name', 'like', "%{$q}%");
            });
        }
        $books = $booksQuery->orderBy('books.title')->limit(30)->get();

        // 5. 장바구니 (세션, vendor별 분리)
        $cartKey = 'cart.'.($vendorId ?? '0').'.'.($selectedAgent->id ?? '0');
        $cart = $request->session()->get($cartKey, []);
        $cartBooks = empty($cart) ? collect() : DB::table('books')->whereIn('id', array_keys($cart))->get()->keyBy('id');

        // 6. 카트 합계 계산
        $cartLines = collect();
        $subtotal = 0;
        foreach ($cart as $bookId => $qty) {
            $book = $cartBooks->get($bookId);
            if (! $book) continue;
            $rate = $bookDiscounts->get($bookId, $selectedAgent->general_rate ?? 0);
            $unitPrice = (int) round($book->price * (100 - $rate) / 100);
            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;
            $cartLines->push([
                'book'        => $book,
                'qty'         => $qty,
                'list_price'  => $book->price,
                'rate'        => $rate,
                'unit_price'  => $unitPrice,
                'line_total'  => $lineTotal,
                'has_book_discount' => $bookDiscounts->has($bookId),
            ]);
        }

        return view('public.mypage.order_new', [
            'user'           => $user,
            'vendor'         => $vendor,
            'agents'         => $agents,
            'selectedAgent'  => $selectedAgent,
            'bookDiscounts'  => $bookDiscounts,
            'books'          => $books,
            'q'              => $q,
            'cartKey'        => $cartKey,
            'cartLines'      => $cartLines,
            'subtotal'       => $subtotal,
        ]);
    }

    /** 장바구니 - 추가 */
    public function cartAdd(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') abort(403);

        $data = $request->validate([
            'book_id'  => ['required', 'integer', 'exists:books,id'],
            'qty'      => ['required', 'integer', 'min:1', 'max:9999'],
            'cart_key' => ['required', 'string'],
        ]);

        $cart = $request->session()->get($data['cart_key'], []);
        $cart[$data['book_id']] = ($cart[$data['book_id']] ?? 0) + $data['qty'];
        $request->session()->put($data['cart_key'], $cart);

        return back()->with('success', '장바구니에 담았습니다.');
    }

    /** 장바구니 - 수량 변경 (일괄) */
    public function cartUpdate(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') abort(403);

        $cartKey = $request->input('cart_key');
        if (! $cartKey) abort(400);

        $qtys = $request->input('qty', []);
        $cart = $request->session()->get($cartKey, []);
        foreach ($qtys as $bookId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                unset($cart[$bookId]);
            } else {
                $cart[$bookId] = min($qty, 9999);
            }
        }
        $request->session()->put($cartKey, $cart);

        return back()->with('success', '장바구니가 업데이트되었습니다.');
    }

    /** 장바구니 - 항목 제거 */
    public function cartRemove(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') abort(403);

        $data = $request->validate([
            'book_id'  => ['required', 'integer'],
            'cart_key' => ['required', 'string'],
        ]);

        $cart = $request->session()->get($data['cart_key'], []);
        unset($cart[$data['book_id']]);
        $request->session()->put($data['cart_key'], $cart);

        return back()->with('success', '제거되었습니다.');
    }

    /** 주문 생성 (장바구니 → 주문) */
    public function storeOrder(Request $request, \App\Services\NotificationService $notify)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') abort(403);

        $data = $request->validate([
            'cart_key' => ['required', 'string'],
            'agent_id' => ['required', 'integer'],
        ]);

        $cart = $request->session()->get($data['cart_key'], []);
        if (empty($cart)) {
            return back()->with('error', '장바구니가 비어있습니다.');
        }

        $vendorId = DB::table('vendor_users')->where('user_id', $user->id)->value('vendor_id');
        if (! $vendorId) {
            return back()->with('error', '학원 매핑이 없습니다.');
        }

        // 영업자 검증 (이 vendor에 매핑된 active agent인지)
        $agentRow = DB::table('agent_vendor_discounts')
            ->where('vendor_id', $vendorId)
            ->where('agent_user_id', $data['agent_id'])
            ->where('is_active', true)
            ->first();
        if (! $agentRow) {
            return back()->with('error', '유효하지 않은 영업자입니다.');
        }

        // 총판 결정 (agent의 첫 distributor)
        $distId = DB::table('user_relations')
            ->where('child_user_id', $agentRow->agent_user_id)
            ->where('relation_type', 'distributor_agent')
            ->where('status', 'active')
            ->orderBy('id')
            ->value('parent_user_id');

        // 책별 할인율
        $bookDiscounts = DB::table('agent_vendor_book_discounts')
            ->where('agent_user_id', $agentRow->agent_user_id)
            ->where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->pluck('discount_rate', 'book_id');

        // 도서 정보 일괄 조회
        $books = DB::table('books')->whereIn('id', array_keys($cart))->whereNull('deleted_at')->get()->keyBy('id');

        // 주문번호 생성 (BS + YYYYMMDD + 4자리 시퀀스)
        $today = date('Ymd');
        $count = DB::table('orders')->whereDate('created_at', today())->count() + 1;
        $orderNo = setting('order_no_prefix', 'BF').$today.str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $subtotal = 0;
        $itemRows = [];
        foreach ($cart as $bookId => $qty) {
            $book = $books->get($bookId);
            if (! $book) continue;
            $rate = (float) $bookDiscounts->get($bookId, $agentRow->discount_rate);
            $unitPrice = (int) round($book->price * (100 - $rate) / 100);
            $lineTotal = $unitPrice * (int) $qty;
            $subtotal += $lineTotal;
            $itemRows[] = [
                'book_id'         => $bookId,
                'isbn_snapshot'   => $book->isbn,
                'title_snapshot'  => $book->title,
                'qty'             => (int) $qty,
                'list_price'      => $book->price,
                'discount_rate'   => $rate,
                'discount_source' => $bookDiscounts->has($bookId) ? 'book' : 'general',
                'unit_price'      => $unitPrice,
                'line_total'      => $lineTotal,
            ];
        }
        if (empty($itemRows)) {
            return back()->with('error', '주문 가능한 도서가 없습니다.');
        }

        $orderId = null;
        DB::transaction(function () use ($orderNo, $vendorId, $agentRow, $distId, $subtotal, $itemRows, $user, &$orderId) {
            $orderId = DB::table('orders')->insertGetId([
                'order_no'            => $orderNo,
                'vendor_id'           => $vendorId,
                'agent_user_id'       => $agentRow->agent_user_id,
                'distributor_user_id' => $distId,
                'subtotal_amount'     => $subtotal,
                'shipping_fee'        => 0,
                'total_amount'        => $subtotal,
                'status_code'         => 'requested',
                'requested_at'        => now(),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
            foreach ($itemRows as $row) {
                $row['order_id']   = $orderId;
                $row['created_at'] = now();
                $row['updated_at'] = now();
                DB::table('order_items')->insert($row);
            }
            DB::table('order_status_logs')->insert([
                'order_id'    => $orderId,
                'from_status' => null,
                'to_status'   => 'requested',
                'changed_by'  => $user->id,
                'reason'      => '학원 주문 접수',
                'created_at'  => now(),
            ]);
        });

        AuditLog::log('orders', $orderId, 'create', null, [
            'order_no' => $orderNo, 'vendor_id' => $vendorId, 'agent_user_id' => $agentRow->agent_user_id,
            'total_amount' => $subtotal, 'item_count' => count($itemRows),
        ]);

        // 장바구니 비우기
        $request->session()->forget($data['cart_key']);

        // 알림 발송 (영업자 + 학원)
        try {
            $vendor = DB::table('vendors')->find($vendorId);
            $agent  = DB::table('users')->find($agentRow->agent_user_id);
            $notify->send('order.requested', [
                'order_no'     => $orderNo,
                'vendor_name'  => $vendor->name ?? '',
                'agent_name'   => $agent->name ?? '',
                'total_amount' => $subtotal,
            ], [
                ['type' => 'user', 'id' => $agent->id, 'phone' => $agent->phone, 'email' => $agent->email],
                ['type' => 'vendor', 'id' => $vendor->id, 'phone' => $vendor->mobile ?? null, 'email' => null],
            ]);
        } catch (\Throwable $e) {
            // 알림 실패는 주문 자체에 영향 X
        }

        return redirect()->route('my.orders.show', $orderId)->with('success', "주문 {$orderNo} 가 접수되었습니다.");
    }

    /** 학급/학생 (학원) */
    public function classesIndex()
    {
        return view('public.mypage.placeholder', [
            'user'  => Auth::user(),
            'title' => '학급/학생',
            'icon'  => 'bi-mortarboard',
            'description' => '학급 편성과 학생/학부모 관리, 학부모 공유링크 발송. 곧 제공됩니다.',
        ]);
    }

    // -------------------- 비밀번호 강제 변경 (첫 로그인 / 관리자 초기화 후) --------------------
    public function showForcePasswordChange()
    {
        $user = Auth::user();
        if (! (bool) $user->password_change_required) {
            // 변경 불필요 → 원래 페이지로
            return $user->role_code === 'admin'
                ? redirect()->route('admin.dashboard')
                : redirect()->route('mypage');
        }
        return view('public.mypage.force_password_change', ['user' => $user]);
    }

    public function submitForcePasswordChange(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'max:50'],
        ], [
            'password.min'     => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.letters' => '비밀번호에 영문자가 1자 이상 포함되어야 합니다.',
            'password.numbers' => '비밀번호에 숫자가 1자 이상 포함되어야 합니다.',
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => '현재 비밀번호가 일치하지 않습니다.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => '새 비밀번호는 기존 비밀번호와 달라야 합니다.']);
        }
        $user->password = $data['password'];
        $user->password_change_required = false;
        $user->save();
        AuditLog::log('users', $user->id, 'force_change_password', null, null);

        return ($user->role_code === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('mypage'))
            ->with('success', '비밀번호가 변경되었습니다.');
    }
}
