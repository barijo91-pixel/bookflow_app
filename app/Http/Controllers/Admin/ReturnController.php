<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 반품 관리 — 전사 기준.
 *
 * 총판이 처리하지 않은 건을 본사가 대신 확정/반려할 수 있다.
 * PG 가맹점이 본사이므로 환불(부분취소) 주체로서도 여기가 최종 창구.
 */
class ReturnController extends Controller
{
    public const VIEWS = [
        'list'        => '반품 목록',
        'vendor'      => '거래처별',
        'book'        => '도서별',
        'reason'      => '사유별',
        'distributor' => '총판별',
    ];

    private function scoped(?int $distId, ?int $agentId)
    {
        $q = DB::table('returns as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->join('vendors as v', 'v.id', '=', 'r.vendor_id')
            ->whereNull('r.deleted_at');

        if ($distId)  $q->where('r.distributor_user_id', $distId);
        if ($agentId) $q->where('r.agent_user_id', $agentId);

        return $q;
    }

    public function index(Request $request)
    {
        $view = $request->query('view', 'list');
        if (! array_key_exists($view, self::VIEWS)) $view = 'list';

        $status = $request->query('status', '');
        if (! array_key_exists($status, ReturnService::STATUS)) $status = '';

        $distId  = (int) $request->query('distributor_id') ?: null;
        $agentId = (int) $request->query('agent_id') ?: null;

        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->query('to')   ?: now()->toDateString();

        $base = fn () => $this->scoped($distId, $agentId)
            ->whereDate('r.requested_at', '>=', $from)
            ->whereDate('r.requested_at', '<=', $to);

        $summary = $base()
            ->selectRaw("
                COUNT(*) as total,
                SUM(r.status = 'requested') as requested,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.total_qty ELSE 0 END) as confirmed_qty,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.total_amount ELSE 0 END) as confirmed_amount,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.refund_amount ELSE 0 END) as refunded_amount,
                SUM(r.status = 'confirmed' AND r.refund_status IN ('failed','partial')) as refund_stuck")
            ->first();

        // 반품률 — 같은 기간·같은 필터의 결제액 대비
        $salesQ = DB::table('payment_requests as pr')
            ->join('orders as o', 'o.id', '=', 'pr.order_id')
            ->where('pr.status', 'paid')
            ->whereNull('o.deleted_at')
            ->whereDate('pr.paid_at', '>=', $from)
            ->whereDate('pr.paid_at', '<=', $to);
        if ($distId)  $salesQ->where('o.distributor_user_id', $distId);
        if ($agentId) $salesQ->where('o.agent_user_id', $agentId);
        $sales = (int) $salesQ->sum('pr.amount');
        $returnRate = $sales > 0 ? round(($summary->confirmed_amount ?? 0) / $sales * 100, 1) : null;

        $rows = match ($view) {
            'list'        => $this->listRows($base(), $status),
            'vendor'      => $this->groupRows($base(), 'v.name'),
            'reason'      => $this->groupRows($base(), 'r.reason_code'),
            'distributor' => $this->groupRows(
                                $base()->leftJoin('users as d', 'd.id', '=', 'r.distributor_user_id'),
                                "COALESCE(NULLIF(d.business_name,''), d.name, '(미배정)')"),
            'book'        => $this->bookRows($from, $to, $distId, $agentId),
        };

        return view('admin.returns.index', [
            'view'       => $view,
            'views'      => self::VIEWS,
            'from'       => $from,
            'to'         => $to,
            'status'     => $status,
            'statuses'   => ReturnService::STATUS,
            'reasons'    => ReturnService::REASONS,
            'distId'     => $distId,
            'agentId'    => $agentId,
            'summary'    => $summary,
            'sales'      => $sales,
            'returnRate' => $returnRate,
            'rows'       => $rows,
            'distributors' => DB::table('users')->where('role_code', 'distributor')
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'business_name']),
            'agents' => DB::table('users')->where('role_code', 'agent')
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'business_name']),
        ]);
    }

    private function listRows($q, string $status)
    {
        if ($status) $q->where('r.status', $status);
        return $q->leftJoin('users as a', 'a.id', '=', 'r.agent_user_id')
            ->leftJoin('users as d2', 'd2.id', '=', 'r.distributor_user_id')
            ->select('r.*', 'o.order_no', 'v.name as vendor_name',
                     'a.name as agent_name', 'd2.name as dist_name')
            ->orderByDesc('r.id')->limit(300)->get();
    }

    private function groupRows($q, string $labelExpr)
    {
        return $q->selectRaw("{$labelExpr} as label,
                COUNT(*) as cnt,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.total_qty ELSE 0 END) as qty,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.total_amount ELSE 0 END) as amount")
            ->groupBy('label')->orderByDesc('amount')->limit(400)->get();
    }

    private function bookRows(string $from, string $to, ?int $distId, ?int $agentId)
    {
        $q = DB::table('return_items as ri')
            ->join('returns as r', 'r.id', '=', 'ri.return_id')
            ->whereNull('r.deleted_at')
            ->whereIn('r.status', ['requested', 'confirmed'])
            ->whereDate('r.requested_at', '>=', $from)
            ->whereDate('r.requested_at', '<=', $to);
        if ($distId)  $q->where('r.distributor_user_id', $distId);
        if ($agentId) $q->where('r.agent_user_id', $agentId);

        return $q->selectRaw("ri.title_snapshot as label,
                COUNT(DISTINCT r.id) as cnt,
                SUM(CASE WHEN r.status = 'confirmed' THEN ri.qty ELSE 0 END) as qty,
                SUM(CASE WHEN r.status = 'confirmed' THEN ri.line_total ELSE 0 END) as amount")
            ->groupBy('label')->orderByDesc('amount')->limit(400)->get();
    }

    /** 상세 (품목 + 환불 이력) — 목록 모달용 JSON */
    public function show(int $id)
    {
        $return = $this->scoped(null, null)->where('r.id', $id)
            ->select('r.*', 'o.order_no', 'v.name as vendor_name')->first();
        abort_if(! $return, 404);

        $items = DB::table('return_items')->where('return_id', $id)
            ->get(['title_snapshot', 'qty', 'unit_price', 'line_total']);
        $refunds = DB::table('return_refunds as rr')
            ->leftJoin('payment_requests as pr', 'pr.id', '=', 'rr.payment_request_id')
            ->where('rr.return_id', $id)
            ->get(['rr.amount', 'rr.status', 'rr.error_message', 'rr.created_at',
                   'pr.parent_name', 'pr.student_name']);

        return response()->json(['return' => $return, 'items' => $items, 'refunds' => $refunds]);
    }

    /** 확정 + PG 부분취소 — 총판이 처리 안 한 건을 본사가 대신 */
    public function confirm(int $id)
    {
        $return = DB::table('returns')->whereNull('deleted_at')->find($id);
        abort_if(! $return, 404);

        try {
            $res = ReturnService::confirm($return, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = match ($res['refund_status']) {
            'done'    => '반품 확정 · 환불 ' . number_format($res['refund_amount']) . '원 완료 (PG 부분취소)',
            'partial' => '반품 확정 · 환불 일부만 성공 (' . number_format($res['refund_amount']) . '원). 실패분은 재시도하세요.',
            'failed'  => '반품은 확정됐지만 환불에 실패했습니다. 재시도하거나 PG 관리자화면에서 확인하세요.',
            default   => '반품 확정 완료. PG 결제가 없는 주문이라 장부 차감만 기록했습니다.',
        };
        $level = in_array($res['refund_status'], ['partial', 'failed'], true) ? 'error' : 'success';
        return back()->with($level, $msg . ($res['errors'] ? ' — ' . implode(' / ', $res['errors']) : ''));
    }

    public function retryRefund(int $id)
    {
        $return = DB::table('returns')->whereNull('deleted_at')->find($id);
        abort_if(! $return, 404);

        try {
            $res = ReturnService::refund($return->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($res['refund_status'] === 'done' ? 'success' : 'error',
            '환불 재시도 — ' . number_format($res['refund_amount']) . '원 처리'
            . ($res['errors'] ? ' / 실패: ' . implode(' / ', $res['errors']) : ''));
    }

    public function reject(Request $request, int $id)
    {
        $return = DB::table('returns')->whereNull('deleted_at')->find($id);
        abort_if(! $return, 404);

        try {
            ReturnService::reject($return, Auth::id(), $request->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', '반품을 반려했습니다.');
    }
}
