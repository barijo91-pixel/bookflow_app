<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users_total'       => DB::table('users')->whereNull('deleted_at')->count(),
            'users_pending'     => DB::table('users')->where('status_code', 'pending')->whereNull('deleted_at')->count(),
            'distributors'      => DB::table('users')->where('role_code', 'distributor')->where('status_code', 'active')->count(),
            'agents'            => DB::table('users')->where('role_code', 'agent')->where('status_code', 'active')->count(),
            'academies_user'    => DB::table('users')->where('role_code', 'academy')->where('status_code', 'active')->count(),
            'vendors'           => DB::table('vendors')->whereNull('deleted_at')->count(),
            'books'             => DB::table('books')->whereNull('deleted_at')->count(),
            'orders_today'      => DB::table('orders')->whereDate('created_at', today())->count(),
            'orders_pending'    => DB::table('orders')->whereIn('status_code', ['requested', 'confirmed', 'accepted'])->count(),
            'amount_total'      => (int) DB::table('orders')->whereNotIn('status_code', ['canceled', 'returned'])->sum('total_amount'),
            'amount_today'      => (int) DB::table('orders')->whereDate('created_at', today())->whereNotIn('status_code', ['canceled'])->sum('total_amount'),
            'low_stocks'        => DB::table('book_stocks')->whereColumn('qty', '<=', 'low_stock_threshold')->count(),
            'notifications_failed' => DB::table('notifications')->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        // 정산 통계 (settlement_records 기반)
        $settlement = DB::table('settlement_records')->selectRaw('
            COUNT(*) as cnt,
            COALESCE(SUM(parent_paid),0) as parent_paid_total,
            COALESCE(SUM(dist_net),0) as dist_net_total,
            COALESCE(SUM(agent_net),0) as agent_net_total,
            COALESCE(SUM(agent_payout),0) as agent_payout_total,
            COALESCE(SUM(pg_fee),0) as pg_fee_total,
            COALESCE(SUM(booksys_fee),0) as booksys_fee_total,
            COALESCE(SUM(CASE WHEN status="paid_out" THEN agent_payout ELSE 0 END),0) as paid_out_total,
            COALESCE(SUM(CASE WHEN status="computed" THEN agent_payout ELSE 0 END),0) as pending_total,
            COUNT(CASE WHEN status="computed" THEN 1 END) as pending_cnt
        ')->first();

        // 최근 30일 매출 추세 (일별)
        $daily = DB::table('orders')
            ->selectRaw('DATE(created_at) as d, SUM(total_amount) as amount, COUNT(*) as cnt')
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->whereNotIn('status_code', ['canceled'])
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        // 활성 사입자 TOP 5 (최근 30일 주문 기준)
        $topAgents = DB::table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.agent_user_id')
            ->whereDate('o.created_at', '>=', now()->subDays(30))
            ->whereNotIn('o.status_code', ['canceled'])
            ->selectRaw('u.id, u.name, COUNT(o.id) as orders_cnt, SUM(o.total_amount) as amount')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        // 활성 학원 TOP 5
        $topVendors = DB::table('orders as o')
            ->join('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->whereDate('o.created_at', '>=', now()->subDays(30))
            ->whereNotIn('o.status_code', ['canceled'])
            ->selectRaw('v.id, v.name, COUNT(o.id) as orders_cnt, SUM(o.total_amount) as amount')
            ->groupBy('v.id', 'v.name')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        // 차트용 데이터 (JSON 직렬화)
        $chartLabels = $daily->pluck('d')->map(fn($d) => substr($d, 5))->values();
        $chartAmounts = $daily->pluck('amount')->map(fn($v) => (int) $v)->values();
        $chartCnt = $daily->pluck('cnt')->map(fn($v) => (int) $v)->values();

        $recentUsers = DB::table('users')
            ->select('id', 'name', 'login_id', 'email', 'role_code', 'status_code', 'created_at')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentOrders = DB::table('orders as o')
            ->leftJoin('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'o.agent_user_id')
            ->select('o.id','o.order_no','o.status_code','o.total_amount','o.created_at',
                'v.name as vendor_name', 'ag.name as agent_name')
            ->whereNull('o.deleted_at')
            ->orderByDesc('o.id')
            ->limit(5)
            ->get();

        $recentNotifications = DB::table('notifications')
            ->select('id', 'event_code', 'channel', 'recipient_phone', 'status', 'created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentAudits = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select('a.id','a.entity','a.entity_id','a.action','a.created_at','u.name as user_name')
            ->orderByDesc('a.id')
            ->limit(5)
            ->get();

        return view('admin.dashboard.index', compact(
            'stats', 'recentUsers', 'recentOrders', 'recentNotifications', 'recentAudits',
            'settlement', 'topAgents', 'topVendors',
            'chartLabels', 'chartAmounts', 'chartCnt'
        ));
    }
}
