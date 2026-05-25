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

    /** 도서 주문하기 (학원) */
    public function orderNew()
    {
        return view('public.mypage.placeholder', [
            'user'  => Auth::user(),
            'title' => '도서 주문하기',
            'icon'  => 'bi-bag-plus',
            'description' => '도서를 검색해 장바구니에 담고 주문하는 페이지. 곧 제공됩니다.',
        ]);
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
