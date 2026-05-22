<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        ]);
        $user->update($data);
        return back()->with('success', '정보가 저장되었습니다.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:4', 'max:50', 'confirmed'],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => '현재 비밀번호가 일치하지 않습니다.']);
        }
        $user->password = $data['password'];
        $user->save();
        return back()->with('success', '비밀번호가 변경되었습니다.');
    }
}
