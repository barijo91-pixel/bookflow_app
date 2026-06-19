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

                // 사입자 온보딩 체크리스트 (계획서 8장)
                $hasBusinessType = ($user->business_type ?? 'none') !== 'none';
                $hasBankAccount  = ! empty($user->bank_account);
                $hasVendor       = $vendorIds->count() > 0;
                $hasOrder        = DB::table('orders')->where('agent_user_id', $user->id)->whereNull('deleted_at')->exists();
                $hasSettlement   = DB::table('settlement_records')->where('agent_user_id', $user->id)->exists();

                $context['onboarding'] = [
                    [
                        'key'       => 'business_type',
                        'label'     => '사업자 유형 등록',
                        'desc'      => '비사업자/간이/일반/법인 — 세무 적용 기준',
                        'done'      => $hasBusinessType,
                        'href'      => route('mypage.profile') . '#business',
                        'icon'      => 'receipt-cutoff',
                    ],
                    [
                        'key'       => 'bank_account',
                        'label'     => '정산 계좌 등록',
                        'desc'      => '수수료 지급받을 은행 계좌',
                        'done'      => $hasBankAccount,
                        'href'      => route('mypage.profile') . '#bank',
                        'icon'      => 'bank',
                    ],
                    [
                        'key'       => 'first_vendor',
                        'label'     => '첫 거래처(학원) 등록',
                        'desc'      => '담당할 학원 등록 — 도매 영업의 시작',
                        'done'      => $hasVendor,
                        'href'      => route('my.vendors.create'),
                        'icon'      => 'building-add',
                    ],
                    [
                        'key'       => 'first_order',
                        'label'     => '첫 주문 접수',
                        'desc'      => '학원에서 도서 주문 받기',
                        'done'      => $hasOrder,
                        'href'      => route('my.orders.index'),
                        'icon'      => 'receipt',
                    ],
                    [
                        'key'       => 'first_settlement',
                        'label'     => '첫 정산 수령',
                        'desc'      => '학부모 결제 완료 시 자동 정산',
                        'done'      => $hasSettlement,
                        'href'      => route('mypage.settlements'),
                        'icon'      => 'cash-stack',
                    ],
                ];
                $context['onboarding_done']  = count(array_filter($context['onboarding'], fn($s) => $s['done']));
                $context['onboarding_total'] = count($context['onboarding']);
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
        $bankOptions = DB::table('codes')->where('group_code', 'bank')->orderBy('sort_order')->get();
        return view('public.mypage.profile', [
            'user' => Auth::user(),
            'bankOptions' => $bankOptions,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $rules = [
            'name'  => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
        // 총판은 PG/입금 계좌도 함께 저장
        if ($user->role_code === 'distributor') {
            $rules['bank_code']    = ['nullable', 'string', 'max:10'];
            $rules['bank_account'] = ['nullable', 'string', 'max:50'];
            $rules['bank_holder']  = ['nullable', 'string', 'max:50'];
        }
        // 영업자(사입자)는 세무 정보 + 계좌
        if ($user->role_code === 'agent') {
            $rules['business_type'] = ['required', 'in:none,individual_simple,individual_general,corporate'];
            $rules['business_no']   = ['nullable', 'string', 'max:20'];
            $rules['business_name'] = ['nullable', 'string', 'max:100'];
            $rules['bank_code']     = ['nullable', 'string', 'max:10'];
            $rules['bank_account']  = ['nullable', 'string', 'max:50'];
            $rules['bank_holder']   = ['nullable', 'string', 'max:50'];
        }
        $data = $request->validate($rules);
        $user->update($data);
        return back()->with('success', '정보가 저장되었습니다.');
    }

    /** 영업자(사입자) 세무 정보 페이지 — 계획서 8-A장 */
    public function taxInfo()
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') {
            abort(403, '영업자만 접근 가능합니다.');
        }

        // 누적 연간 수수료 (PG 정산 전까지는 주문 기반 추정)
        $yearStart = now()->startOfYear()->toDateString();
        $estimated = DB::table('orders as o')
            ->join('agent_vendor_discounts as avd', function ($j) use ($user) {
                $j->on('avd.vendor_id', '=', 'o.vendor_id')
                  ->where('avd.agent_user_id', '=', $user->id);
            })
            ->where('o.agent_user_id', $user->id)
            ->whereDate('o.created_at', '>=', $yearStart)
            ->whereNull('o.deleted_at')
            ->whereIn('o.status_code', ['confirmed', 'accepted', 'shipped', 'in_transit', 'completed'])
            ->sum(DB::raw('o.subtotal_amount * (avd.discount_rate / 100)'));
        // 마진율 자체가 수수료 추정의 일부. 실제 정산률(예: 8%p × 0.4 사입자 분배)은 PG에서.
        // 임시: 도서 거래 마진의 40% (계획서 6:4 분배 기준) 추정
        $estimatedCommission = (int) round($estimated * 0.4);

        $taxCalc = \App\Services\TaxService::calc($user->business_type ?? 'none', $estimatedCommission);
        $stageInfo = \App\Services\TaxService::checkStage($estimatedCommission, $user->business_type ?? 'none');

        return view('public.mypage.tax_info', [
            'user'                => $user,
            'estimatedCommission' => $estimatedCommission,
            'taxCalc'             => $taxCalc,
            'stageInfo'           => $stageInfo,
            'types'               => \App\Services\TaxService::TYPES,
        ]);
    }

    /**
     * 사입자 정산 내역 (본인 정산 레코드 + 누적 통계)
     * 총판은 본인 수금 정산 모두 표시
     */
    public function settlements(Request $request)
    {
        $user = Auth::user();
        if (! in_array($user->role_code, ['agent', 'distributor'])) {
            abort(403, '영업자/총판만 접근 가능합니다.');
        }

        $q = \App\Models\SettlementRecord::with(['vendor', 'paymentRequest'])
            ->orderByDesc('id');

        if ($user->role_code === 'agent') {
            $q->where('agent_user_id', $user->id);
        } else { // distributor
            $q->where('distributor_user_id', $user->id);
        }

        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }

        $records = $q->paginate(20)->withQueryString();

        // 누적 통계 (필터 무관 — 전체)
        $base = \App\Models\SettlementRecord::query();
        if ($user->role_code === 'agent') {
            $base->where('agent_user_id', $user->id);
        } else {
            $base->where('distributor_user_id', $user->id);
        }

        $stats = (clone $base)->selectRaw('
            COUNT(*) as cnt,
            COALESCE(SUM(parent_paid),0) as parent_paid_total,
            COALESCE(SUM(agent_net),0) as agent_net_total,
            COALESCE(SUM(agent_payout),0) as agent_payout_total,
            COALESCE(SUM(dist_net),0) as dist_net_total,
            COALESCE(SUM(CASE WHEN status="paid_out" THEN agent_payout ELSE 0 END),0) as paid_out_total,
            COALESCE(SUM(CASE WHEN status="computed" THEN agent_payout ELSE 0 END),0) as pending_total
        ')->first();

        return view('public.mypage.settlements', compact('records', 'stats', 'user', 'status'));
    }

    /**
     * 사입자/총판용 수익 시뮬레이션 — 본인 학원/도서/할인율 기반
     */
    public function incomeSimulator(Request $request)
    {
        $user = Auth::user();
        if (! in_array($user->role_code, ['agent', 'distributor'])) {
            abort(403, '영업자/총판만 접근 가능합니다.');
        }

        $vendorId   = $request->input('vendor_id');
        $unitPrice  = (int) $request->input('unit_price', 16000);
        $qty        = (int) $request->input('qty', 30);
        $splitRatio = '6:4'; // 향후 사입자별 다른 비율 적용 가능

        // 본인 거래처 목록
        if ($user->role_code === 'agent') {
            $vendors = DB::table('agent_vendor_discounts as avd')
                ->join('vendors as v', 'v.id', '=', 'avd.vendor_id')
                ->where('avd.agent_user_id', $user->id)
                ->where('avd.is_active', true)
                ->select('v.id', 'v.name', 'avd.discount_rate')
                ->orderBy('v.name')->get();
        } else {
            $vendors = DB::table('vendors')->select('id', 'name')
                ->selectRaw('30 as discount_rate')
                ->orderBy('name')->get();
        }

        // 선택된 학원의 할인율 사용 (없으면 30%)
        $discountRate = 30.0;
        if ($vendorId) {
            $found = $vendors->firstWhere('id', (int) $vendorId);
            if ($found) {
                $discountRate = (float) $found->discount_rate;
            }
        }

        $b2b = \App\Services\SettlementService::calcB2B($unitPrice, $qty, $discountRate, $splitRatio);
        $b2c = \App\Services\SettlementService::calcB2C($unitPrice, $qty, 0, $splitRatio);

        $businessType = $user->business_type ?? 'none';
        $b2bTax = \App\Services\TaxService::calc($businessType, max(0, $b2b['agent_total_margin']));
        $b2cTax = \App\Services\TaxService::calc($businessType, max(0, $b2c['agent_net']));

        return view('public.mypage.income_simulator', [
            'user'         => $user,
            'vendors'      => $vendors,
            'unitPrice'    => $unitPrice,
            'qty'          => $qty,
            'discountRate' => $discountRate,
            'vendorId'     => $vendorId,
            'b2b'          => $b2b,
            'b2c'          => $b2c,
            'b2bTax'       => $b2bTax,
            'b2cTax'       => $b2cTax,
            'businessType' => $businessType,
            'splitRatio'   => $splitRatio,
        ]);
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
        // 학원도 본인 주문이고 아직 'requested' 상태면 취소 가능 / 영업자·총판은 더 넓은 범위
        $canCancel = false;
        if (in_array($order->status_code, ['requested','confirmed','accepted'])) {
            if ($user->role_code === 'agent' && $order->agent_user_id == $user->id) $canCancel = true;
            if ($user->role_code === 'distributor' && $order->distributor_user_id == $user->id) $canCancel = true;
        }
        if ($order->status_code === 'requested' && $user->role_code === 'academy') {
            $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
            if ($vendorIds->contains($order->vendor_id)) $canCancel = true;
        }

        $canEdit = $this->canEditOrder($order, $user);

        // 이 주문으로 결제요청된 구매 학부모 (소매 — 결제요청 단계에서 연결됨)
        $payers = DB::table('payment_requests')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get(['student_name', 'parent_name', 'parent_phone', 'amount', 'status', 'paid_at']);

        return view('public.mypage.order_show', compact(
            'user', 'order', 'vendor', 'agent', 'dist', 'items', 'statusLogs', 'shipment',
            'courierOptions', 'canConfirm', 'canAccept', 'canShip', 'canCancel', 'canEdit', 'payers'
        ));
    }

    /** 주문 수정 폼 (학원, requested 상태) */
    public function editOrder($id)
    {
        $order = \App\Models\Order::findOrFail($id);
        $user  = $this->authorizeOrder($order);

        if (! $this->canEditOrder($order, $user)) {
            return redirect()->route('my.orders.show', $order->id)
                ->with('error', '수정 불가: 본인 학원의 접수 대기(requested) 상태 주문만 수정할 수 있습니다.');
        }

        $vendor = DB::table('vendors')->find($order->vendor_id);
        $items  = DB::table('order_items as oi')
            ->leftJoin('books as b', 'b.id', '=', 'oi.book_id')
            ->where('oi.order_id', $order->id)
            ->select('oi.*', 'b.cover_path', 'b.isbn as book_isbn', 'b.title as book_title')
            ->orderBy('oi.id')->get();

        return view('public.mypage.order_edit', compact('user', 'order', 'vendor', 'items'));
    }

    /** 주문 수정 적용 (수량 변경 + 행 삭제) */
    public function updateOrder(Request $request, $id)
    {
        $order = \App\Models\Order::findOrFail($id);
        $user  = $this->authorizeOrder($order);

        if (! $this->canEditOrder($order, $user)) {
            return redirect()->route('my.orders.show', $order->id)
                ->with('error', '수정 불가: 본인 학원의 접수 대기(requested) 상태 주문만 수정할 수 있습니다.');
        }

        $data = $request->validate([
            'items'           => ['required', 'array', 'min:1'],
            'items.*.id'      => ['required', 'integer'],
            'items.*.qty'     => ['required', 'integer', 'min:0', 'max:99999'],
            'items.*.delete'  => ['nullable', 'in:0,1'],
            'reason'          => ['nullable', 'string', 'max:500'],
        ]);

        $existingItems = DB::table('order_items')->where('order_id', $order->id)->get()->keyBy('id');
        $beforeSnapshot = [
            'total_amount' => $order->total_amount,
            'items' => $existingItems->map(fn ($r) => ['book_id' => $r->book_id, 'qty' => $r->qty, 'line_total' => $r->line_total])->values()->all(),
        ];

        $newSubtotal = 0;
        $toUpdate = []; // [id => qty]
        $toDelete = []; // [id, ...]
        $keptCount = 0;

        foreach ($data['items'] as $row) {
            $itemId = (int) $row['id'];
            $item   = $existingItems->get($itemId);
            if (! $item) continue; // 본인 주문 아닌 행은 무시

            $deleting = (! empty($row['delete']) && $row['delete'] == '1') || (int) $row['qty'] === 0;
            if ($deleting) {
                $toDelete[] = $itemId;
                continue;
            }

            $qty = (int) $row['qty'];
            $lineTotal = (int) $item->unit_price * $qty;
            $toUpdate[$itemId] = ['qty' => $qty, 'line_total' => $lineTotal];
            $newSubtotal += $lineTotal;
            $keptCount++;
        }

        if ($keptCount === 0) {
            return back()->withInput()->with('error', '최소 1개 도서는 남겨야 합니다. 전체 삭제는 "주문 취소"를 사용하세요.');
        }

        DB::transaction(function () use ($order, $toUpdate, $toDelete, $newSubtotal, $user, $data) {
            foreach ($toUpdate as $itemId => $up) {
                DB::table('order_items')->where('id', $itemId)->update([
                    'qty'        => $up['qty'],
                    'line_total' => $up['line_total'],
                    'updated_at' => now(),
                ]);
            }
            if (! empty($toDelete)) {
                DB::table('order_items')->whereIn('id', $toDelete)->delete();
            }
            DB::table('orders')->where('id', $order->id)->update([
                'subtotal_amount' => $newSubtotal,
                'total_amount'    => $newSubtotal, // shipping_fee=0 가정 (기존과 동일)
                'updated_at'      => now(),
            ]);
            DB::table('order_status_logs')->insert([
                'order_id'    => $order->id,
                'from_status' => 'requested',
                'to_status'   => 'requested',
                'changed_by'  => $user->id,
                'reason'      => '주문 수정: '.($data['reason'] ?? '학원에서 수량 변경/도서 삭제'),
                'created_at'  => now(),
            ]);
        });

        $afterSnapshot = [
            'total_amount' => $newSubtotal,
            'deleted_item_ids' => $toDelete,
            'updated_count' => count($toUpdate),
        ];
        AuditLog::log('orders', $order->id, 'update_items', $beforeSnapshot, $afterSnapshot);

        return redirect()->route('my.orders.show', $order->id)
            ->with('success', '주문이 수정되었습니다. (총액 '.number_format($newSubtotal).'원)');
    }

    /** 주문 수정 가능 여부: 학원 본인 vendor + requested 상태 */
    private function canEditOrder($order, $user): bool
    {
        if ($order->status_code !== 'requested') return false;
        if ($user->role_code !== 'academy') return false;
        $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
        return $vendorIds->contains($order->vendor_id);
    }

    /** 상태 전이 (영업자 confirm, 총판 accept, 취소 등) */
    public function transitionOrder(Request $request, $id, \App\Services\NotificationService $notify)
    {
        $order = \App\Models\Order::findOrFail($id);
        $user  = $this->authorizeOrder($order);

        $data = $request->validate([
            'to_status'     => ['required', 'in:confirmed,accepted,canceled'],
            'reason'        => ['nullable', 'string', 'max:500'],
            'delivery_type' => ['nullable', 'in:parcel,direct'], // 영업자 confirm 시 배송 방식 선택
        ]);
        $to = $data['to_status'];
        $from = $order->status_code;

        // 역할별 허용 전이 확인
        $allowed = false;
        if ($to === 'confirmed' && $user->role_code === 'agent' && $from === 'requested' && $order->agent_user_id == $user->id) $allowed = true;
        if ($to === 'accepted'  && $user->role_code === 'distributor' && $from === 'confirmed' && $order->distributor_user_id == $user->id) $allowed = true;
        if ($to === 'canceled') {
            // 영업자/총판: requested~accepted 까지
            if (in_array($from, ['requested','confirmed','accepted'])) {
                if ($user->role_code === 'agent' && $order->agent_user_id == $user->id) $allowed = true;
                if ($user->role_code === 'distributor' && $order->distributor_user_id == $user->id) $allowed = true;
            }
            // 학원: 본인 학원 주문이고 아직 'requested' 일 때만
            if ($from === 'requested' && $user->role_code === 'academy') {
                $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
                if ($vendorIds->contains($order->vendor_id)) $allowed = true;
            }
        }
        if (! $allowed) {
            return back()->with('error', "상태 전이 불가 ({$from} → {$to})");
        }

        DB::transaction(function () use ($order, $to, $from, $data) {
            $update = ['status_code' => $to, 'updated_at' => now()];
            switch ($to) {
                case 'confirmed':
                    $update['confirmed_at'] = now();
                    // 영업자 확정 시 배송 방식 저장 (없으면 parcel 유지)
                    if (! empty($data['delivery_type'])) {
                        $update['delivery_type'] = $data['delivery_type'];
                    }
                    break;
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

        // 배송 방식에 따라 분기 — 계획서 6-2장 (직접배송 신규 필요)
        $isDirect = ($order->delivery_type ?? 'parcel') === 'direct';

        if ($isDirect) {
            // 직접배송: 기사 정보 입력
            $data = $request->validate([
                'driver_name'  => ['required', 'string', 'max:50'],
                'driver_phone' => ['required', 'string', 'max:20'],
                'vehicle_no'   => ['nullable', 'string', 'max:20'],
                'delivery_fee' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            ]);
            $shipmentData = [
                'driver_name'      => $data['driver_name'],
                'driver_phone'     => preg_replace('/[^0-9]/', '', $data['driver_phone']),
                'vehicle_no'       => $data['vehicle_no'] ?? null,
                'delivery_fee'     => $data['delivery_fee'] ?? 0,
                'dispatched_at'    => now(),
                'ship_status_code' => 'shipped',
                'shipped_at'       => now(),
            ];
            $logReason = "직접배송 배차: 기사 {$data['driver_name']} ({$data['driver_phone']})";
            $notifyExtra = [
                'driver_name'  => $data['driver_name'],
                'driver_phone' => $data['driver_phone'],
                'vehicle_no'   => $data['vehicle_no'] ?? '',
                'delivery_fee' => $data['delivery_fee'] ?? 0,
            ];
        } else {
            // 택배: 택배사 + 송장번호
            $data = $request->validate([
                'courier_code' => ['required', 'string', 'max:30'],
                'tracking_no'  => ['required', 'string', 'max:50'],
            ]);
            $shipmentData = [
                'courier_code'     => $data['courier_code'],
                'tracking_no'      => $data['tracking_no'],
                'ship_status_code' => 'shipped',
                'shipped_at'       => now(),
            ];
            $logReason = '송장입력: '.$data['courier_code'].' '.$data['tracking_no'];
            $courierName = DB::table('codes')->where('group_code', 'courier')->where('code', $data['courier_code'])->value('name') ?? $data['courier_code'];
            $notifyExtra = [
                'courier_name' => $courierName,
                'tracking_no'  => $data['tracking_no'],
            ];
        }

        DB::transaction(function () use ($order, $shipmentData, $logReason) {
            DB::table('order_shipments')->updateOrInsert(
                ['order_id' => $order->id],
                array_merge($shipmentData, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
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
                'reason'     => $logReason,
                'created_at' => now(),
            ]);
        });

        AuditLog::log('orders', $order->id, 'ship',
            ['status_code' => 'accepted'],
            array_merge(['status_code' => 'shipped'], $shipmentData));

        $this->dispatchOrderNotification($order->fresh(), 'shipped', $notify, null, $notifyExtra);

        return back()->with('success', $isDirect ? '직접배송 배차 정보 저장 완료' : '출고 처리 완료');
    }

    /** 영업자가 [직접배송 신청] 클릭 — 계획서 6-2장 */
    public function requestDirectDelivery(Request $request, $id)
    {
        $order = \App\Models\Order::findOrFail($id);
        $user  = Auth::user();
        if ($user->role_code !== 'agent' || $order->agent_user_id != $user->id) {
            abort(403, '영업자 본인 주문만 신청 가능합니다.');
        }
        if (! in_array($order->status_code, ['confirmed', 'accepted'], true)) {
            return back()->with('error', '확정/접수 상태에서만 직접배송 신청 가능합니다.');
        }

        $data = $request->validate([
            'delivery_memo' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($order, $data) {
            DB::table('orders')->where('id', $order->id)->update([
                'delivery_type' => 'direct',
                'delivery_memo' => $data['delivery_memo'] ?? null,
                'updated_at'    => now(),
            ]);
            DB::table('order_shipments')->updateOrInsert(
                ['order_id' => $order->id],
                ['direct_requested_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        });

        AuditLog::log('orders', $order->id, 'direct_delivery_request',
            null, ['delivery_type' => 'direct', 'memo' => $data['delivery_memo'] ?? null]);

        return back()->with('success', '직접배송 신청 완료 — 총판에게 알림이 전송됩니다.');
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
        $status   = $request->query('status');
        // 디폴트: 최근 30일 (출고·완료 등 지난 주문도 보이도록)
        $dateFrom = $request->query('date_from') ?: now()->subDays(30)->format('Y-m-d');
        $dateTo   = $request->query('date_to')   ?: now()->format('Y-m-d');
        $q        = trim((string) $request->query('q'));
        $tradeType = $request->query('trade_type'); // retail/wholesale 필터 (영업자·총판용)

        $query = DB::table('orders as o')
            ->leftJoin('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'o.agent_user_id')
            ->leftJoin('users as ds', 'ds.id', '=', 'o.distributor_user_id')
            ->whereNull('o.deleted_at')
            ->select(
                'o.id', 'o.order_no', 'o.status_code', 'o.total_amount',
                'o.requested_at', 'o.confirmed_at', 'o.accepted_at',
                'o.shipped_at', 'o.completed_at', 'o.created_at',
                'v.name as vendor_name', 'v.trade_type',
                'ag.name as agent_name', 'ag.login_id as agent_login_id',
                DB::raw("COALESCE(NULLIF(ds.business_name, ''), ds.name) as distributor_name")
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

        if ($status)   $query->where('o.status_code', $status);
        if ($tradeType) $query->where('v.trade_type', $tradeType);
        if ($dateFrom) $query->whereDate('o.created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('o.created_at', '<=', $dateTo);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('o.order_no', 'like', "%{$q}%")
                  ->orWhere('v.name', 'like', "%{$q}%");
            });
        }

        $orders = $query->orderByDesc('o.id')->paginate(20)->withQueryString();

        // 상태별 카운트 (필터 UI용) — 목록과 동일한 날짜 범위 적용 (카운트=목록 일치)
        $statusBaseQuery = DB::table('orders')->whereNull('deleted_at');
        switch ($user->role_code) {
            case 'agent':       $statusBaseQuery->where('agent_user_id', $user->id); break;
            case 'distributor': $statusBaseQuery->where('distributor_user_id', $user->id); break;
            case 'academy':     $statusBaseQuery->whereIn('vendor_id', DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id')); break;
        }
        if ($dateFrom) $statusBaseQuery->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $statusBaseQuery->whereDate('created_at', '<=', $dateTo);
        $statusCounts = $statusBaseQuery->select('status_code', DB::raw('count(*) as cnt'))
            ->groupBy('status_code')->pluck('cnt', 'status_code');

        return view('public.mypage.orders', [
            'user'   => $user,
            'orders' => $orders,
            'title'  => $title,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'q'        => $q,
            'tradeType' => $tradeType,
            'statusCounts' => $statusCounts,
        ]);
    }

    /** 재고 관리 (총판) - Phase B-6 */
    public function stocksIndex(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'distributor') {
            abort(403, '총판만 접근 가능합니다.');
        }

        $q   = trim((string) $request->query('q'));
        $low = $request->boolean('low');  // 안전재고 이하만 보기

        $query = DB::table('book_stocks as s')
            ->join('books as b', 'b.id', '=', 's.book_id')
            ->leftJoin('publishers as p', 'p.id', '=', 'b.publisher_id')
            ->where('s.distributor_user_id', $user->id)
            ->whereNull('b.deleted_at')
            ->select(
                's.id as stock_id', 's.qty', 's.low_stock_threshold', 's.reserved_qty',
                'b.id as book_id', 'b.isbn', 'b.title', 'b.subtitle', 'b.school_code', 'b.subject_code',
                'b.price', 'p.name as publisher_name'
            );
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('b.title', 'like', "%{$q}%")
                  ->orWhere('b.isbn', 'like', "%{$q}%");
            });
        }
        if ($low) {
            $query->whereColumn('s.qty', '<=', 's.low_stock_threshold');
        }
        $stocks = $query->orderBy('b.title')->paginate(30)->withQueryString();

        // 요약
        $baseStocks = DB::table('book_stocks')->where('distributor_user_id', $user->id);
        $summary = [
            'total_books' => (clone $baseStocks)->count(),
            'total_qty'   => (int) (clone $baseStocks)->sum('qty'),
            'low_stock'   => (clone $baseStocks)->whereColumn('qty', '<=', 'low_stock_threshold')->count(),
            'zero_stock'  => (clone $baseStocks)->where('qty', 0)->count(),
        ];

        // 등록 가능한 책 목록 (아직 재고 안 잡힌 책)
        $myBookIds = DB::table('book_stocks')->where('distributor_user_id', $user->id)->pluck('book_id');
        $availableBooks = DB::table('books')
            ->whereNull('deleted_at')->where('status_code', 'selling')
            ->whereNotIn('id', $myBookIds)
            ->orderBy('title')->get(['id', 'title', 'isbn', 'price']);

        return view('public.mypage.stocks', compact('user', 'stocks', 'summary', 'availableBooks', 'q', 'low'));
    }

    /** 재고 수정 (qty / low_stock_threshold) */
    public function stockUpdate(Request $request, $stockId)
    {
        $user = Auth::user();
        if ($user->role_code !== 'distributor') abort(403);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $row = DB::table('book_stocks')->where('id', $stockId)->where('distributor_user_id', $user->id)->first();
        if (! $row) abort(404);

        DB::table('book_stocks')->where('id', $stockId)->update([
            'qty' => $data['qty'],
            'low_stock_threshold' => $data['low_stock_threshold'] ?? $row->low_stock_threshold,
            'updated_at' => now(),
        ]);

        AuditLog::log('book_stocks', $stockId, 'update', ['qty' => $row->qty], ['qty' => $data['qty']]);
        return back()->with('success', '재고가 업데이트되었습니다.');
    }

    /** 신규 도서 재고 등록 */
    public function stockStore(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'distributor') abort(403);

        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'qty' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        // 중복 방지
        $exists = DB::table('book_stocks')->where('book_id', $data['book_id'])
            ->where('distributor_user_id', $user->id)->exists();
        if ($exists) {
            return back()->with('error', '이미 등록된 도서입니다.');
        }

        DB::table('book_stocks')->insert([
            'book_id' => $data['book_id'],
            'distributor_user_id' => $user->id,
            'qty' => $data['qty'],
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
            'reserved_qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', '도서 재고가 등록되었습니다.');
    }

    /** 재고 항목 제거 */
    public function stockDestroy($stockId)
    {
        $user = Auth::user();
        if ($user->role_code !== 'distributor') abort(403);

        $row = DB::table('book_stocks')->where('id', $stockId)->where('distributor_user_id', $user->id)->first();
        if (! $row) abort(404);

        DB::table('book_stocks')->where('id', $stockId)->delete();
        return back()->with('success', '재고 항목이 제거되었습니다.');
    }

    /** 소속 영업자 (총판) - Phase B-9 */
    public function agentsIndex(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'distributor') {
            abort(403, '총판만 접근 가능합니다.');
        }

        $q       = trim((string) $request->query('q'));
        $sidoId  = (int) $request->query('sido_id');
        $sigungu = (int) $request->query('sigungu_id');

        // user_relations 에서 본 총판의 영업자 (active)
        $query = DB::table('user_relations as r')
            ->join('users as u', 'u.id', '=', 'r.child_user_id')
            ->leftJoin('regions as rg', 'rg.id', '=', 'u.region_id')
            ->leftJoin('regions as p', 'p.id', '=', 'rg.parent_id')
            ->where('r.parent_user_id', $user->id)
            ->where('r.relation_type', 'distributor_agent')
            ->where('r.status', 'active')
            ->select(
                'u.id', 'u.login_id', 'u.name', 'u.phone', 'u.email', 'u.status_code',
                'u.last_login_at', 'u.approved_at', 'u.region_id',
                'rg.name as sigungu_name', 'p.name as sido_name', 'p.id as sido_id',
                'r.started_at'
            );

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('u.name', 'like', "%{$q}%")
                  ->orWhere('u.login_id', 'like', "%{$q}%")
                  ->orWhere('u.phone', 'like', "%{$q}%");
            });
        }
        if ($sigungu) {
            $query->where('u.region_id', $sigungu);
        } elseif ($sidoId) {
            $query->where('p.id', $sidoId);
        }

        $agents = $query->orderBy('u.name')->get();

        // 지역 필터 옵션
        $sidos = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get(['id', 'name']);
        $sigungus = collect();
        if ($sidoId) {
            $sigungus = DB::table('regions')->where('parent_id', $sidoId)->where('level', 'sigungu')
                ->orderBy('sort_order')->get(['id', 'name']);
        }

        // 각 영업자가 담당하는 학원 수 + 처리 주문 수
        $agentIds = $agents->pluck('id')->toArray();
        if (! empty($agentIds)) {
            // 담당 학원 수
            $vendorCounts = DB::table('agent_vendor_discounts')
                ->whereIn('agent_user_id', $agentIds)
                ->where('is_active', true)
                ->select('agent_user_id', DB::raw('count(*) as cnt'))
                ->groupBy('agent_user_id')->pluck('cnt', 'agent_user_id');
            // 주문 (이 총판으로 들어온 것 중 영업자가 처리한 것)
            $orderCounts = DB::table('orders')
                ->where('distributor_user_id', $user->id)
                ->whereIn('agent_user_id', $agentIds)
                ->whereNotIn('status_code', ['canceled', 'returned'])
                ->whereNull('deleted_at')
                ->select('agent_user_id', DB::raw('count(*) as cnt'), DB::raw('sum(total_amount) as amt'))
                ->groupBy('agent_user_id')->get()->keyBy('agent_user_id');
            foreach ($agents as $a) {
                $a->vendor_count = $vendorCounts[$a->id] ?? 0;
                $a->order_count  = isset($orderCounts[$a->id]) ? $orderCounts[$a->id]->cnt : 0;
                $a->order_amount = isset($orderCounts[$a->id]) ? (int) $orderCounts[$a->id]->amt : 0;
            }
        }

        return view('public.mypage.agents', compact(
            'user', 'agents', 'q', 'sidoId', 'sigungu', 'sidos', 'sigungus'
        ));
    }

    /** 담당 학원 (영업자) */
    public function vendorsIndex(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') {
            abort(403, '영업자만 접근 가능합니다.');
        }

        $q = trim((string) $request->query('q'));

        $query = DB::table('agent_vendor_discounts as avd')
            ->join('vendors as v', 'v.id', '=', 'avd.vendor_id')
            ->leftJoin('regions as r', 'r.id', '=', 'v.region_id')
            ->leftJoin('regions as p', 'p.id', '=', 'r.parent_id')
            ->where('avd.agent_user_id', $user->id)
            ->select(
                'avd.id as avd_id',
                'v.id', 'v.name', 'v.owner_name', 'v.business_no',
                'v.mobile', 'v.tel', 'v.status_code',
                'avd.discount_rate', 'avd.is_active as discount_active',
                'avd.started_at', 'avd.ended_at',
                'r.name as sigungu_name', 'p.name as sido_name'
            );

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('v.name', 'like', "%{$q}%")
                  ->orWhere('v.owner_name', 'like', "%{$q}%");
            });
        }

        $vendors = $query
            ->orderByDesc('avd.is_active')
            ->orderBy('v.name')
            ->get();

        return view('public.mypage.vendors', [
            'user' => $user,
            'vendors' => $vendors,
            'q' => $q,
        ]);
    }

    /** 할인율 관리 (영업자) - Phase B-7 */
    public function discountsIndex(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') {
            abort(403, '영업자만 접근 가능합니다.');
        }

        // 본인 담당 학원들 — 활성(거래중)만. 중단된 학원은 거래처 페이지에서 재활성화 후 노출.
        $vendors = DB::table('agent_vendor_discounts as avd')
            ->join('vendors as v', 'v.id', '=', 'avd.vendor_id')
            ->where('avd.agent_user_id', $user->id)
            ->where('avd.is_active', true)
            ->whereNull('v.deleted_at')
            ->select(
                'avd.id as avd_id', 'avd.discount_rate as general_rate', 'avd.is_active',
                'v.id as vendor_id', 'v.name as vendor_name'
            )
            ->orderBy('v.name')->get();

        // 선택된 학원
        $selectedVendorId = (int) $request->query('vendor_id', $vendors->first()->vendor_id ?? 0);
        $selectedVendor = $vendors->firstWhere('vendor_id', $selectedVendorId);

        // 도서별 개별 할인율 (선택된 학원)
        $bookDiscounts = collect();
        $availableBooks = collect();
        if ($selectedVendor) {
            $bookDiscounts = DB::table('agent_vendor_book_discounts as avbd')
                ->join('books as b', 'b.id', '=', 'avbd.book_id')
                ->where('avbd.agent_user_id', $user->id)
                ->where('avbd.vendor_id', $selectedVendorId)
                ->select(
                    'avbd.id as avbd_id', 'avbd.discount_rate', 'avbd.is_active',
                    'b.id as book_id', 'b.isbn', 'b.title', 'b.price'
                )
                ->orderBy('b.title')->get();

            $existingBookIds = $bookDiscounts->pluck('book_id')->toArray();
            $availableBooks = DB::table('books')
                ->whereNull('deleted_at')->where('status_code', 'selling')
                ->whereNotIn('id', $existingBookIds)
                ->orderBy('title')->get(['id', 'title', 'isbn', 'price']);
        }

        return view('public.mypage.discounts', compact(
            'user', 'vendors', 'selectedVendor', 'selectedVendorId', 'bookDiscounts', 'availableBooks'
        ));
    }

    /** 학원별 일반 할인율 수정 */
    public function discountVendorUpdate(Request $request, $avdId)
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') abort(403);

        $data = $request->validate([
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        $row = DB::table('agent_vendor_discounts')->where('id', $avdId)
            ->where('agent_user_id', $user->id)->first();
        if (! $row) abort(404);

        DB::table('agent_vendor_discounts')->where('id', $avdId)->update([
            'discount_rate' => $data['discount_rate'],
            'is_active'     => $request->boolean('is_active'),
            'updated_at'    => now(),
        ]);

        AuditLog::log('agent_vendor_discounts', $avdId, 'update',
            ['discount_rate' => $row->discount_rate],
            ['discount_rate' => $data['discount_rate']]);
        return back()->with('success', '학원 할인율이 저장되었습니다.');
    }

    /** 도서별 개별 할인율 추가/수정 */
    public function discountBookUpsert(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') abort(403);

        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'book_id'   => ['required', 'integer', 'exists:books,id'],
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        // 영업자가 이 학원에 매핑되어 있는지 검증
        $hasVendor = DB::table('agent_vendor_discounts')
            ->where('agent_user_id', $user->id)
            ->where('vendor_id', $data['vendor_id'])->exists();
        if (! $hasVendor) abort(403, '담당하지 않는 학원입니다.');

        DB::table('agent_vendor_book_discounts')->updateOrInsert(
            ['agent_user_id' => $user->id, 'vendor_id' => $data['vendor_id'], 'book_id' => $data['book_id']],
            ['discount_rate' => $data['discount_rate'], 'is_active' => true,
             'started_at' => now()->toDateString(), 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('success', '도서 개별 할인율이 저장되었습니다.');
    }

    /** 학원별 할인율 매핑 비활성화 (소프트 삭제) */
    public function discountVendorDestroy($avdId)
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') abort(403);

        $row = DB::table('agent_vendor_discounts')->where('id', $avdId)
            ->where('agent_user_id', $user->id)->first();
        if (! $row) abort(404);

        DB::table('agent_vendor_discounts')->where('id', $avdId)->update([
            'is_active'  => false,
            'ended_at'   => now()->toDateString(),
            'updated_at' => now(),
        ]);

        AuditLog::log('agent_vendor_discounts', $avdId, 'deactivate',
            ['is_active' => (bool) $row->is_active],
            ['is_active' => false]);

        return back()->with('success', '거래가 일시 중단되었습니다. (재시작은 관리자에게 문의)');
    }

    /** 도서별 개별 할인율 제거 */
    public function discountBookDestroy($avbdId)
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') abort(403);

        $row = DB::table('agent_vendor_book_discounts')->where('id', $avbdId)
            ->where('agent_user_id', $user->id)->first();
        if (! $row) abort(404);

        DB::table('agent_vendor_book_discounts')->where('id', $avbdId)->delete();
        return back()->with('success', '개별 할인율이 제거되었습니다.');
    }

    /** 도서 주문하기 (학원) - 검색 + 장바구니 + 주문 생성 */
    public function orderNew(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') {
            // 주문 생성은 학원만. 영업자/총판은 주문 목록으로, 그 외는 대시보드로 안내
            if (in_array($user->role_code, ['agent', 'distributor'])) {
                return redirect()->route('my.orders.index')
                    ->with('info', '도서 주문은 학원 계정에서 올립니다. 영업자/총판은 올라온 주문을 확인·처리하세요.');
            }
            return redirect()->route('mypage')
                ->with('info', '도서 주문은 학원 계정에서만 가능합니다.');
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

        // 4. 도서 검색 + 필터 + 목록
        $q        = trim((string) $request->query('q'));
        $school   = $request->query('school');     // 분류 (elementary/middle/high/general)
        $subject  = $request->query('subject');    // 과목 (korean/english/math/science/social)
        $grade    = $request->query('grade');      // 학년 (pre_elem/elem_1~6/mid_1~3/high_1~3)
        $semester = $request->query('semester');   // 학기 (sem_1/sem_2)
        $publisher = $request->query('publisher');  // 출판사 id

        $booksQuery = DB::table('books')
            ->whereNull('books.deleted_at')
            ->where('books.status_code', 'selling')
            ->leftJoin('publishers as p', 'p.id', '=', 'books.publisher_id')
            ->select('books.*', 'p.name as publisher_name');
        if ($q !== '') {
            $booksQuery->where(function ($w) use ($q) {
                $w->where('books.title', 'like', "%{$q}%")
                  ->orWhere('books.isbn', 'like', "%{$q}%")
                  ->orWhere('books.series_name', 'like', "%{$q}%")
                  ->orWhere('books.author', 'like', "%{$q}%");
            });
        }
        if ($school)   $booksQuery->where('books.school_code', $school);
        if ($subject)  $booksQuery->where('books.subject_code', $subject);
        if ($publisher) $booksQuery->where('books.publisher_id', $publisher);
        // 학년/학기는 book_targets (M:N) 조회
        if ($grade) {
            $booksQuery->whereExists(function ($q) use ($grade) {
                $q->select(DB::raw(1))->from('book_targets')
                  ->whereColumn('book_targets.book_id', 'books.id')
                  ->where('target_type', 'grade')
                  ->where('code', $grade);
            });
        }
        if ($semester) {
            $booksQuery->whereExists(function ($q) use ($semester) {
                $q->select(DB::raw(1))->from('book_targets')
                  ->whereColumn('book_targets.book_id', 'books.id')
                  ->where('target_type', 'semester')
                  ->where('code', $semester);
            });
        }

        $books = $booksQuery->orderBy('books.title')->limit(60)->get();

        // 필터 옵션 (codes 테이블에서, 분류에 따라 학년 동적 필터)
        $allGrades = DB::table('codes')->where('group_code', 'grade')->orderBy('sort_order')->get(['code','name']);
        $gradePrefix = match($school) {
            'elementary' => ['pre_elem', 'elem_'],
            'middle'     => ['mid_'],
            'high'       => ['high_'],
            default      => null, // 분류 미선택 or 단행본 → 학년 표시 안 함
        };
        $filteredGrades = collect();
        if ($gradePrefix !== null) {
            $filteredGrades = $allGrades->filter(function ($g) use ($gradePrefix) {
                foreach ($gradePrefix as $prefix) {
                    if (str_starts_with($g->code, $prefix)) return true;
                }
                return false;
            })->values();
        }

        $filterOptions = [
            'school'   => DB::table('codes')->where('group_code', 'school')->orderBy('sort_order')->get(['code','name']),
            'subject'  => DB::table('codes')->where('group_code', 'subject')->orderBy('sort_order')->get(['code','name']),
            'grade'    => $filteredGrades,
            'semester' => DB::table('codes')->where('group_code', 'semester')->orderBy('sort_order')->get(['code','name']),
            'publisher' => DB::table('publishers as p')
                ->whereIn('p.id', function ($sq) {
                    $sq->select('publisher_id')->from('books')
                       ->whereNull('deleted_at')->where('status_code', 'selling')->whereNotNull('publisher_id');
                })
                ->orderBy('p.sort_order')->orderBy('p.name')->get(['p.id as code', 'p.name']),
        ];
        $activeFilters = compact('school','subject','grade','semester','q','publisher');
        $showSubFilters = (bool) $school; // 분류 선택 시에만 하위 필터 표시
        $showGradeRow   = $school && $school !== 'general'; // 단행본은 학년 의미 없음
        $showSemesterRow= $school && $school !== 'general'; // 단행본은 학기 의미 없음

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
            'filterOptions'  => $filterOptions,
            'activeFilters'  => $activeFilters,
            'showSubFilters' => $showSubFilters,
            'showGradeRow'   => $showGradeRow,
            'showSemesterRow'=> $showSemesterRow,
        ]);
    }

    /**
     * 바코드 스캔 → 장바구니 추가 (ISBN 기반)
     * - 핸디 바코드 리더기: 키보드 입력 → Enter
     * - 응답은 JSON (페이지 reload 없이 추가)
     */
    public function cartScanAdd(Request $request)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') {
            return response()->json(['ok' => false, 'msg' => '학원만 사용 가능합니다.'], 403);
        }

        $data = $request->validate([
            'isbn'     => ['required', 'string', 'max:30'],
            'cart_key' => ['required', 'string'],
            'qty'      => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        // ISBN 정제 (숫자/X만 남김)
        $isbn = preg_replace('/[^0-9Xx]/', '', $data['isbn']);
        if (strlen($isbn) !== 13 && strlen($isbn) !== 10) {
            return response()->json(['ok' => false, 'msg' => "ISBN 형식이 올바르지 않습니다 ({$isbn})"], 422);
        }

        $book = DB::table('books')
            ->whereNull('deleted_at')
            ->where('isbn', $isbn)
            ->where('status_code', 'selling')
            ->select('id', 'isbn', 'title', 'price')
            ->first();
        if (! $book) {
            return response()->json(['ok' => false, 'msg' => "도서를 찾을 수 없습니다 (ISBN: {$isbn})"], 404);
        }

        $qty = (int) ($data['qty'] ?? 1);
        $cart = $request->session()->get($data['cart_key'], []);
        $cart[$book->id] = ($cart[$book->id] ?? 0) + $qty;
        $request->session()->put($data['cart_key'], $cart);

        return response()->json([
            'ok'       => true,
            'book'     => $book,
            'qty'      => $cart[$book->id], // 누적 수량
            'added'    => $qty,
            'msg'      => "{$book->title} ({$qty}권) 추가 — 현재 {$cart[$book->id]}권",
            'cart_count' => count($cart),
            'cart_total_qty' => array_sum($cart),
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

    // ============== 학급/학생 (학원 전용) — Phase B-8 ==============

    /** 학원 사용자의 vendor_id 가져오기 (없으면 abort) */
    private function academyVendor(): array
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') {
            abort(403, '학원만 접근 가능합니다.');
        }
        $vendorId = DB::table('vendor_users')->where('user_id', $user->id)->value('vendor_id');
        if (! $vendorId) {
            abort(403, '학원이 연결되지 않은 계정입니다. 관리자에게 문의해주세요.');
        }
        $vendor = DB::table('vendors')->find($vendorId);
        return [$user, $vendorId, $vendor];
    }

    /** 학급 목록 */
    public function classesIndex()
    {
        [$user, $vendorId, $vendor] = $this->academyVendor();

        $classes = DB::table('academy_classes')
            ->where('vendor_id', $vendorId)
            ->orderByDesc('id')
            ->get();

        // 각 학급 학생 수
        $classIds = $classes->pluck('id')->toArray();
        $counts = DB::table('students')
            ->whereIn('class_id', $classIds)
            ->whereNull('deleted_at')
            ->select('class_id', DB::raw('count(*) as cnt'))
            ->groupBy('class_id')->pluck('cnt', 'class_id');
        foreach ($classes as $c) {
            $c->student_count = $counts[$c->id] ?? 0;
        }

        $grades = DB::table('codes')->where('group_code', 'grade')->orderBy('sort_order')->get();

        return view('public.mypage.classes', compact('user', 'vendor', 'classes', 'grades'));
    }

    /** 학급 생성 */
    public function classesStore(Request $request)
    {
        [$user, $vendorId] = $this->academyVendor();

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'grade_code' => ['nullable', 'string', 'max:30'],
            'started_at' => ['nullable', 'date'],
            'ended_at'   => ['nullable', 'date'],
            'memo'       => ['nullable', 'string', 'max:1000'],
        ]);

        $id = DB::table('academy_classes')->insertGetId([
            'vendor_id'  => $vendorId,
            'name'       => $data['name'],
            'grade_code' => $data['grade_code'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'ended_at'   => $data['ended_at'] ?? null,
            'memo'       => $data['memo'] ?? null,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('my.classes.show', $id)->with('success', '학급이 등록되었습니다.');
    }

    /** 학급 상세 */
    public function classesShow($id)
    {
        [$user, $vendorId] = $this->academyVendor();

        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404, '학급을 찾을 수 없습니다.');

        $students = DB::table('students as s')
            ->leftJoin('parents as p', 'p.id', '=', 's.parent_id')
            ->where('s.class_id', $id)
            ->whereNull('s.deleted_at')
            ->select('s.id', 's.name', 's.grade_code', 's.memo',
                'p.id as parent_id', 'p.name as parent_name', 'p.phone as parent_phone')
            ->orderBy('s.id')->get();

        $books = DB::table('class_books as cb')
            ->leftJoin('books as b', 'b.id', '=', 'cb.book_id')
            ->where('cb.class_id', $id)
            ->select('cb.id as cb_id', 'cb.qty', 'cb.sort_order',
                'b.id as book_id', 'b.title', 'b.isbn', 'b.price')
            ->orderBy('cb.sort_order')->orderBy('cb.id')->get();

        $shareLinks = DB::table('parent_share_links as l')
            ->leftJoin('students as s', 's.id', '=', 'l.student_id')
            ->leftJoin('parents as p', 'p.id', '=', 'l.parent_id')
            ->where('l.class_id', $id)
            ->orderByDesc('l.id')->limit(20)
            ->select('l.id', 'l.token', 'l.sent_at', 'l.expires_at', 'l.accessed_at', 'l.access_count',
                's.name as student_name', 'p.name as parent_name', 'p.phone as parent_phone')
            ->get();

        $grades = DB::table('codes')->where('group_code', 'grade')->orderBy('sort_order')->get();
        $availableBooks = DB::table('books as b')
            ->leftJoin('publishers as p', 'p.id', '=', 'b.publisher_id')
            ->whereNull('b.deleted_at')->where('b.status_code', 'selling')
            ->orderBy('b.title')
            ->get(['b.id', 'b.title', 'b.isbn', 'b.price', 'b.publisher_id', 'p.name as publisher_name']);

        // 출판사 필터 옵션 (판매중 도서를 보유한 출판사만)
        $publisherOptions = DB::table('publishers as p')
            ->whereIn('p.id', function ($q) {
                $q->select('publisher_id')->from('books')
                  ->whereNull('deleted_at')->where('status_code', 'selling')->whereNotNull('publisher_id');
            })
            ->orderBy('p.sort_order')->orderBy('p.name')
            ->get(['p.id', 'p.name']);

        return view('public.mypage.class_show', compact(
            'user', 'class', 'students', 'books', 'shareLinks', 'grades', 'availableBooks', 'publisherOptions'
        ));
    }

    /** 학급 정보 수정 */
    public function classesUpdate(Request $request, $id)
    {
        [$user, $vendorId] = $this->academyVendor();
        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'grade_code' => ['nullable', 'string', 'max:30'],
            'started_at' => ['nullable', 'date'],
            'ended_at'   => ['nullable', 'date'],
            'memo'       => ['nullable', 'string', 'max:1000'],
            'status'     => ['nullable', \Illuminate\Validation\Rule::in(['active','closed'])],
        ]);
        $data['updated_at'] = now();
        DB::table('academy_classes')->where('id', $id)->update($data);
        return back()->with('success', '학급 정보가 저장되었습니다.');
    }

    /** 학급 삭제 */
    public function classesDestroy($id)
    {
        [$user, $vendorId] = $this->academyVendor();
        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404);

        $hasStudents = DB::table('students')->where('class_id', $id)->whereNull('deleted_at')->exists();
        if ($hasStudents) {
            return back()->with('error', '소속 학생이 있어 삭제할 수 없습니다. 학생을 먼저 제거해주세요.');
        }
        DB::table('academy_classes')->where('id', $id)->delete();
        return redirect()->route('my.classes.index')->with('success', '학급이 삭제되었습니다.');
    }

    /** 학생 + 학부모 한번에 추가 */
    public function classAttachStudent(Request $request, $id)
    {
        [$user, $vendorId] = $this->academyVendor();
        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404);

        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:80'],
            'grade_code'   => ['nullable', 'string', 'max:30'],
            'parent_name'  => ['required', 'string', 'max:80'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'parent_address'        => ['nullable', 'string', 'max:255'],
            'parent_address_detail' => ['nullable', 'string', 'max:100'],
            'memo'         => ['nullable', 'string', 'max:500'],
        ]);

        $now = now();
        $phone = preg_replace('/[^0-9]/', '', $data['parent_phone']);

        DB::transaction(function () use ($id, $vendorId, $data, $phone, $now) {
            // 학부모: 같은 전화면 재사용
            $parentId = DB::table('parents')->where('phone', $phone)->whereNull('deleted_at')->value('id');
            if (! $parentId) {
                $parentId = DB::table('parents')->insertGetId([
                    'name'       => $data['parent_name'],
                    'phone'      => $phone,
                    'address'        => $data['parent_address'] ?? null,
                    'address_detail' => $data['parent_address_detail'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif (! empty($data['parent_address'])) {
                // 기존 학부모 — 주소가 입력됐으면 갱신
                DB::table('parents')->where('id', $parentId)->update([
                    'address'        => $data['parent_address'],
                    'address_detail' => $data['parent_address_detail'] ?? null,
                    'updated_at'     => $now,
                ]);
            }
            DB::table('students')->insert([
                'vendor_id'  => $vendorId,
                'class_id'   => $id,
                'parent_id'  => $parentId,
                'name'       => $data['student_name'],
                'grade_code' => $data['grade_code'] ?? null,
                'memo'       => $data['memo'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return back()->with('success', '학생이 추가되었습니다.');
    }

    /** 학생 제거 (soft delete) */
    public function classDetachStudent($id, $sid)
    {
        [$user, $vendorId] = $this->academyVendor();
        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404);

        DB::table('students')->where('id', $sid)->where('class_id', $id)->update([
            'deleted_at' => now(), 'updated_at' => now()
        ]);
        return back()->with('success', '학생이 제거되었습니다.');
    }

    /** 학급에 도서 추가 */
    public function classAttachBook(Request $request, $id)
    {
        [$user, $vendorId] = $this->academyVendor();
        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404);

        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'qty'     => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        DB::table('class_books')->updateOrInsert(
            ['class_id' => $id, 'book_id' => $data['book_id']],
            ['qty' => $data['qty'], 'created_at' => now(), 'updated_at' => now()]
        );
        return back()->with('success', '교재가 추가되었습니다.');
    }

    /** 학급 도서 제거 */
    public function classDetachBook($id, $cbid)
    {
        [$user, $vendorId] = $this->academyVendor();
        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404);

        DB::table('class_books')->where('id', $cbid)->where('class_id', $id)->delete();
        return back()->with('success', '교재가 제거되었습니다.');
    }

    /** 학부모 공유링크 생성 + 알림톡 발송 */
    public function classCreateShareLink(Request $request, $id, \App\Services\NotificationService $notify)
    {
        [$user, $vendorId, $vendor] = $this->academyVendor();
        $class = DB::table('academy_classes')->where('id', $id)->where('vendor_id', $vendorId)->first();
        if (! $class) abort(404);

        $data = $request->validate([
            'student_id'  => ['required', 'integer'],
            'ttl_days'    => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $student = DB::table('students')->where('id', $data['student_id'])->where('class_id', $id)->whereNull('deleted_at')->first();
        if (! $student) abort(404, '학생을 찾을 수 없습니다.');
        $parent = DB::table('parents')->find($student->parent_id);
        if (! $parent) return back()->with('error', '학부모 정보가 없습니다.');

        $token = \Illuminate\Support\Str::random(48);
        $ttl = (int) ($data['ttl_days'] ?? 30);

        DB::table('parent_share_links')->insert([
            'class_id'   => $id,
            'student_id' => $student->id,
            'parent_id'  => $parent->id,
            'token'      => $token,
            'sent_at'    => now(),
            'expires_at' => now()->addDays($ttl),
            'access_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = url("/p/{$token}");

        // 알림톡 발송 (실패해도 링크 생성은 유지)
        try {
            $notify->send('b2c.share_link', [
                'academy_name' => $vendor->name ?? '',
                'student_name' => $student->name,
                'link_url'     => $url,
            ], [
                ['type' => 'parent', 'id' => $parent->id, 'phone' => $parent->phone, 'email' => $parent->email],
            ]);
        } catch (\Throwable $e) {
            // skip
        }

        return back()->with('success', "공유링크 발행 완료")->with('share_url', $url);
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
