<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 매출 조회 — 전사 기준.
 *
 * 마이페이지(총판·영업자)판과 집계 방식은 같다 (결제 완료 `payment_requests`).
 * 다른 점은 스코프가 없다는 것 — 대신 총판/영업자로 걸러 볼 수 있고
 * 보기에 '총판별'이 추가된다.
 */
class SalesReportController extends Controller
{
    public const VIEWS = [
        'daily'       => '일별',
        'monthly'     => '월별',
        'vendor'      => '거래처별',
        'book'        => '도서별',
        'publisher'   => '출판사별',
        'agent'       => '영업담당자별',
        'distributor' => '총판별',
    ];

    public const TRADES = ['' => '전체', 'retail' => '소매', 'wholesale' => '도매', 'both' => '도·소매'];

    /** 전사 결제 완료분 + 선택 필터 */
    private function scoped(string $from, string $to, ?string $trade, ?int $distId, ?int $agentId)
    {
        $q = DB::table('payment_requests as pr')
            ->join('orders as o', 'o.id', '=', 'pr.order_id')
            ->join('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->where('pr.status', 'paid')
            ->whereNull('o.deleted_at')
            ->whereDate('pr.paid_at', '>=', $from)
            ->whereDate('pr.paid_at', '<=', $to);

        if ($trade)   $q->where('v.trade_type', $trade);
        if ($distId)  $q->where('o.distributor_user_id', $distId);
        if ($agentId) $q->where('o.agent_user_id', $agentId);

        return $q;
    }

    public function index(Request $request)
    {
        $view = $request->query('view', 'daily');
        if (! array_key_exists($view, self::VIEWS)) $view = 'daily';

        $trade = $request->query('trade');
        if (! array_key_exists((string) $trade, self::TRADES)) $trade = null;
        if ($trade === '') $trade = null;

        $distId  = (int) $request->query('distributor_id') ?: null;
        $agentId = (int) $request->query('agent_id') ?: null;

        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->query('to')   ?: now()->toDateString();

        $base = fn () => $this->scoped($from, $to, $trade, $distId, $agentId);

        $summary = (clone $base())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(pr.amount),0) as revenue,
                         COUNT(DISTINCT pr.order_id) as orders, COUNT(DISTINCT o.vendor_id) as vendors')
            ->first();

        // 소매/도매 비교 — 거래구분 필터와 무관하게 항상
        $byTrade = $this->scoped($from, $to, null, $distId, $agentId)
            ->selectRaw('v.trade_type, COUNT(*) as cnt, COALESCE(SUM(pr.amount),0) as revenue')
            ->groupBy('v.trade_type')->get()->keyBy('trade_type');

        $rows = match ($view) {
            'daily'       => $this->byDate($base(), '%Y-%m-%d'),
            'monthly'     => $this->byDate($base(), '%Y-%m'),
            'vendor'      => $this->byLabel($base(), 'v.name'),
            'agent'       => $this->byLabel(
                                $base()->leftJoin('users as u', 'u.id', '=', 'o.agent_user_id'),
                                "COALESCE(NULLIF(u.business_name,''), u.name)"),
            'distributor' => $this->byLabel(
                                $base()->leftJoin('users as d', 'd.id', '=', 'o.distributor_user_id'),
                                "COALESCE(NULLIF(d.business_name,''), d.name, '(미배정)')"),
            'book'        => $this->byBook($from, $to, $trade, $distId, $agentId, 'book'),
            'publisher'   => $this->byBook($from, $to, $trade, $distId, $agentId, 'publisher'),
        };

        return view('admin.sales.index', [
            'view'    => $view,
            'views'   => self::VIEWS,
            'trade'   => $trade,
            'trades'  => self::TRADES,
            'from'    => $from,
            'to'      => $to,
            'distId'  => $distId,
            'agentId' => $agentId,
            'summary' => $summary,
            'byTrade' => $byTrade,
            'rows'    => $rows,
            'distributors' => DB::table('users')->where('role_code', 'distributor')
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'business_name']),
            'agents' => DB::table('users')->where('role_code', 'agent')
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'business_name']),
        ]);
    }

    private function byDate($q, string $format)
    {
        return $q->selectRaw("DATE_FORMAT(pr.paid_at, '{$format}') as label,
                              COUNT(*) as cnt,
                              COUNT(DISTINCT pr.order_id) as orders,
                              COALESCE(SUM(pr.amount),0) as revenue")
            ->groupBy('label')->orderByDesc('label')->limit(400)->get();
    }

    private function byLabel($q, string $labelExpr)
    {
        return $q->selectRaw("{$labelExpr} as label,
                              COUNT(*) as cnt,
                              COUNT(DISTINCT pr.order_id) as orders,
                              COALESCE(SUM(pr.amount),0) as revenue")
            ->groupBy('label')->orderByDesc('revenue')->limit(400)->get();
    }

    /** 도서별·출판사별 — 결제는 주문 단위라 도서 금액 비중으로 안분 */
    private function byBook(string $from, string $to, ?string $trade, ?int $distId, ?int $agentId, string $mode)
    {
        $paidPerOrder = $this->scoped($from, $to, $trade, $distId, $agentId)
            ->selectRaw('pr.order_id, SUM(pr.amount) as paid')
            ->groupBy('pr.order_id');

        $orderTotal = DB::table('order_items')
            ->selectRaw('order_id, SUM(line_total) as total')
            ->groupBy('order_id');

        $label = $mode === 'book'
            ? 'COALESCE(b.title, oi.title_snapshot)'
            : "COALESCE(p.name, '(출판사 미지정)')";

        return DB::query()
            ->fromSub($paidPerOrder, 'sp')
            ->join('order_items as oi', 'oi.order_id', '=', 'sp.order_id')
            ->joinSub($orderTotal, 'ot', fn ($j) => $j->on('ot.order_id', '=', 'sp.order_id'))
            ->leftJoin('books as b', 'b.id', '=', 'oi.book_id')
            ->leftJoin('publishers as p', 'p.id', '=', 'b.publisher_id')
            ->where('ot.total', '>', 0)
            ->selectRaw("{$label} as label,
                         COUNT(DISTINCT sp.order_id) as orders,
                         SUM(oi.qty) as cnt,
                         ROUND(SUM(sp.paid * oi.line_total / ot.total)) as revenue")
            ->groupBy('label')->orderByDesc('revenue')->limit(400)->get();
    }
}
