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
            'stats', 'recentUsers', 'recentOrders', 'recentNotifications', 'recentAudits'
        ));
    }
}
