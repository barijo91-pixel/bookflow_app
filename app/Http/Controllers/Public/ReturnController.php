<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 반품 관리 (영업자·총판)
 *
 *  - 접수: 영업자·총판 (주문 상세에서 품목별 수량 지정)
 *  - 확정: 총판 — 확정 시 PG 부분취소 자동 (ReturnService)
 *  - 반려: 총판 / 취소: 접수자
 *
 * 보기: 목록(상태 탭) · 거래처별 · 도서별 · 사유별 + 반품률 요약
 */
class ReturnController extends Controller
{
    public const VIEWS = [
        'list'   => '반품 목록',
        'vendor' => '거래처별',
        'book'   => '도서별',
        'reason' => '사유별',
    ];

    private function authorizeUser(): User
    {
        $user = Auth::user();
        if (! $user || ! in_array($user->role_code, ['agent', 'distributor'], true)) {
            abort(403, '영업자 또는 총판만 접근 가능합니다.');
        }
        return $user;
    }

    /** 역할 스코프 — 매출 조회와 같은 기준 (영업자=담당 주문, 총판=산하 주문) */
    private function scoped(User $user)
    {
        $q = DB::table('returns as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->join('vendors as v', 'v.id', '=', 'r.vendor_id')
            ->whereNull('r.deleted_at');

        if ($user->role_code === 'agent') {
            $q->where('r.agent_user_id', $user->id);
        } else {
            $q->where('r.distributor_user_id', $user->id);
        }
        return $q;
    }

    public function index(Request $request)
    {
        $user = $this->authorizeUser();

        $view = $request->query('view', 'list');
        if (! array_key_exists($view, self::VIEWS)) $view = 'list';

        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->query('to')   ?: now()->toDateString();

        $status = $request->query('status', '');
        if (! array_key_exists($status, ReturnService::STATUS)) $status = '';

        $base = fn () => $this->scoped($user)
            ->whereDate('r.requested_at', '>=', $from)
            ->whereDate('r.requested_at', '<=', $to);

        // 요약 — 확정 기준 금액 + 접수 대기 건수
        $summary = $base()
            ->selectRaw("
                COUNT(*) as total,
                SUM(r.status = 'requested') as requested,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.total_qty ELSE 0 END) as confirmed_qty,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.total_amount ELSE 0 END) as confirmed_amount,
                SUM(CASE WHEN r.status = 'confirmed' THEN r.refund_amount ELSE 0 END) as refunded_amount")
            ->first();

        // 반품률 — 같은 기간·같은 스코프의 매출(결제액) 대비 확정 반품액
        $salesQ = DB::table('payment_requests as pr')
            ->join('orders as o', 'o.id', '=', 'pr.order_id')
            ->where('pr.status', 'paid')
            ->whereNull('o.deleted_at')
            ->whereDate('pr.paid_at', '>=', $from)
            ->whereDate('pr.paid_at', '<=', $to);
        if ($user->role_code === 'agent') $salesQ->where('o.agent_user_id', $user->id);
        else $salesQ->where('o.distributor_user_id', $user->id);
        $sales = (int) $salesQ->sum('pr.amount');
        $returnRate = $sales > 0 ? round(($summary->confirmed_amount ?? 0) / $sales * 100, 1) : null;

        $rows = match ($view) {
            'list'   => $this->listRows($base(), $status),
            'vendor' => $this->groupRows($base(), 'v.name'),
            'reason' => $this->groupRows($base(), 'r.reason_code'),
            'book'   => $this->bookRows($user, $from, $to),
        };

        return view('public.mypage.returns', [
            'user'       => $user,
            'view'       => $view,
            'views'      => self::VIEWS,
            'from'       => $from,
            'to'         => $to,
            'status'     => $status,
            'statuses'   => ReturnService::STATUS,
            'reasons'    => ReturnService::REASONS,
            'summary'    => $summary,
            'sales'      => $sales,
            'returnRate' => $returnRate,
            'rows'       => $rows,
        ]);
    }

    private function listRows($q, string $status)
    {
        if ($status) $q->where('r.status', $status);
        return $q->leftJoin('users as a', 'a.id', '=', 'r.agent_user_id')
            ->select('r.*', 'o.order_no', 'v.name as vendor_name', 'a.name as agent_name')
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

    private function bookRows(User $user, string $from, string $to)
    {
        $q = DB::table('return_items as ri')
            ->join('returns as r', 'r.id', '=', 'ri.return_id')
            ->whereNull('r.deleted_at')
            ->whereIn('r.status', ['requested', 'confirmed'])
            ->whereDate('r.requested_at', '>=', $from)
            ->whereDate('r.requested_at', '<=', $to);
        if ($user->role_code === 'agent') $q->where('r.agent_user_id', $user->id);
        else $q->where('r.distributor_user_id', $user->id);

        return $q->selectRaw("ri.title_snapshot as label,
                COUNT(DISTINCT r.id) as cnt,
                SUM(CASE WHEN r.status = 'confirmed' THEN ri.qty ELSE 0 END) as qty,
                SUM(CASE WHEN r.status = 'confirmed' THEN ri.line_total ELSE 0 END) as amount")
            ->groupBy('label')->orderByDesc('amount')->limit(400)->get();
    }

    /** 접수 — 주문 상세에서 품목별 수량으로 */
    public function store(Request $request, int $orderId)
    {
        $user = $this->authorizeUser();

        $order = DB::table('orders')->where('id', $orderId)->whereNull('deleted_at')->first();
        abort_if(! $order, 404);
        if ($user->role_code === 'agent') {
            abort_if((int) $order->agent_user_id !== (int) $user->id, 403);
        } else {
            abort_if((int) $order->distributor_user_id !== (int) $user->id, 403);
        }
        if (! in_array($order->status_code, ReturnService::RETURNABLE_ORDER_STATUS, true)) {
            return back()->with('error', '총판 접수 이후의 주문만 반품할 수 있습니다.');
        }

        $data = $request->validate([
            'items'              => ['required', 'array'],
            'items.*'            => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reason_code'        => ['required', 'in:' . implode(',', array_keys(ReturnService::REASONS))],
            'reason_text'        => ['nullable', 'string', 'max:500'],
            'payment_request_id' => ['nullable', 'integer'],   // 환불 대상 학부모 결제 (소매)
        ]);

        try {
            $return = ReturnService::create($order, $data['items'], $data['reason_code'],
                $data['reason_text'] ?? null, $user->id,
                $data['payment_request_id'] ?? null ? (int) $data['payment_request_id'] : null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('my.returns.index')
            ->with('success', "반품 {$return->return_no} 접수 완료 — {$return->total_qty}권 / "
                . number_format($return->total_amount) . '원. 총판 확정 시 환불됩니다.');
    }

    /** 확정 + PG 부분취소 — 총판만 */
    public function confirm(int $id)
    {
        $user = $this->authorizeUser();
        abort_if($user->role_code !== 'distributor', 403, '반품 확정은 총판만 가능합니다.');

        $return = $this->scoped($user)->where('r.id', $id)->select('r.*')->first();
        abort_if(! $return, 404);

        try {
            $res = ReturnService::confirm($return, $user->id);
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

    /** 환불 재시도 — 확정됐지만 부분취소 실패/일부인 반품 */
    public function retryRefund(int $id)
    {
        $user = $this->authorizeUser();
        abort_if($user->role_code !== 'distributor', 403);

        $return = $this->scoped($user)->where('r.id', $id)->select('r.*')->first();
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

    /** 반려 — 총판만 */
    public function reject(Request $request, int $id)
    {
        $user = $this->authorizeUser();
        abort_if($user->role_code !== 'distributor', 403);

        $return = $this->scoped($user)->where('r.id', $id)->select('r.*')->first();
        abort_if(! $return, 404);

        try {
            ReturnService::reject($return, $user->id, $request->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', '반품을 반려했습니다.');
    }

    /** 취소 — 접수 상태에서 접수자·총판 */
    public function cancel(int $id)
    {
        $user = $this->authorizeUser();

        $return = $this->scoped($user)->where('r.id', $id)->select('r.*')->first();
        abort_if(! $return, 404);
        if ($user->role_code === 'agent' && (int) $return->requested_by !== (int) $user->id) {
            abort(403, '본인이 접수한 반품만 취소할 수 있습니다.');
        }

        try {
            ReturnService::cancel($return, $user->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', '반품 접수를 취소했습니다.');
    }

    /** 상세 (품목 + 환불 이력) — 목록에서 접이식으로 쓰기 위한 JSON */
    public function show(int $id)
    {
        $user = $this->authorizeUser();
        $return = $this->scoped($user)->where('r.id', $id)
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
}
