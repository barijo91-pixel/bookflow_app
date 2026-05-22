<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $entity = $request->query('entity');
        $action = $request->query('action');
        $userId = $request->query('user');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $q      = trim((string) $request->query('q'));

        $query = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select('a.*', 'u.name as user_name', 'u.email as user_email')
            ->orderByDesc('a.id');

        if ($entity) $query->where('a.entity', $entity);
        if ($action) $query->where('a.action', $action);
        if ($userId) $query->where('a.user_id', $userId);
        if ($from)   $query->where('a.created_at', '>=', $from . ' 00:00:00');
        if ($to)     $query->where('a.created_at', '<=', $to   . ' 23:59:59');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('a.entity', 'like', "%{$q}%")
                  ->orWhere('a.action', 'like', "%{$q}%")
                  ->orWhere('a.ip_address', 'like', "%{$q}%");
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        $entities = DB::table('audit_logs')->distinct()->orderBy('entity')->pluck('entity')->filter()->values();
        $actions  = DB::table('audit_logs')->distinct()->orderBy('action')->pluck('action')->filter()->values();
        $userOptions = DB::table('users')
            ->whereIn('id', DB::table('audit_logs')->distinct()->pluck('user_id')->filter())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $summary = [
            'total'  => DB::table('audit_logs')->count(),
            'today'  => DB::table('audit_logs')->whereDate('created_at', today())->count(),
            'week'   => DB::table('audit_logs')->where('created_at', '>=', now()->subDays(7))->count(),
            'users'  => DB::table('audit_logs')->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
        ];

        return view('admin.audit_logs.index', compact(
            'logs', 'entities', 'actions', 'userOptions',
            'entity', 'action', 'userId', 'from', 'to', 'q', 'summary'
        ));
    }

    public function show($id)
    {
        $log = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.id', $id)
            ->select('a.*', 'u.name as user_name', 'u.email as user_email')
            ->first();
        abort_if(! $log, 404);

        $before = $log->before ? json_decode($log->before, true) : null;
        $after  = $log->after  ? json_decode($log->after,  true) : null;

        $diff = [];
        if ($before && $after) {
            $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
            foreach ($keys as $k) {
                $b = $before[$k] ?? null;
                $a = $after[$k]  ?? null;
                if ($b !== $a) {
                    $diff[$k] = ['before' => $b, 'after' => $a];
                }
            }
        }

        return view('admin.audit_logs.show', compact('log', 'before', 'after', 'diff'));
    }
}
