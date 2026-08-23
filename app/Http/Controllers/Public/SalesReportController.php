<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 매출 조회 — 결제 완료된 금액 기준.
 *
 * 출처는 `payment_requests` (status=paid).
 * 소매(학부모 개별 결제)와 도매(학원 직접 결제)가 **둘 다 여기 남는다**.
 * settlement_records 는 소매 결제에서만 생성되므로 매출 집계 기준으로는 쓸 수 없다.
 *
 * 도서별·출판사별은 결제가 주문 단위라 도서 구분이 없어,
 * 결제액을 그 주문의 도서 금액 비중으로 안분해 계산한다.
 */
class SalesReportController extends Controller
{
    public const VIEWS = [
        'daily'     => '일별',
        'monthly'   => '월별',
        'vendor'    => '거래처별',
        'book'      => '도서별',
        'publisher' => '출판사별',
        'agent'     => '영업담당자별',
    ];

    public const TRADES = [
        ''          => '전체',
        'retail'    => '소매',
        'wholesale' => '도매',
    ];

    private function authorizeUser(): User
    {
        $user = Auth::user();
        if (! $user || ! in_array($user->role_code, ['agent', 'distributor'], true)) {
            abort(403, '영업자 또는 총판만 접근 가능합니다.');
        }
        return $user;
    }

    /**
     * 결제 완료분 스코프.
     * 주문을 통해 역할(영업자/총판)과 거래구분(소매/도매)을 건다.
     */
    private function scoped(User $user, string $from, string $to, ?string $trade)
    {
        $q = DB::table('payment_requests as pr')
            ->join('orders as o', 'o.id', '=', 'pr.order_id')
            ->join('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->where('pr.status', 'paid')
            ->whereNull('o.deleted_at')
            ->whereDate('pr.paid_at', '>=', $from)
            ->whereDate('pr.paid_at', '<=', $to);

        if ($user->role_code === 'agent') {
            $q->where('o.agent_user_id', $user->id);
        } else {
            $q->where('o.distributor_user_id', $user->id);
        }
        if ($trade) $q->where('v.trade_type', $trade);

        return $q;
    }

    public function index(Request $request)
    {
        $user = $this->authorizeUser();

        $view = $request->query('view', 'daily');
        if (! array_key_exists($view, self::VIEWS)) $view = 'daily';
        if ($view === 'agent' && $user->role_code === 'agent') $view = 'daily';

        $trade = $request->query('trade');
        if (! array_key_exists((string) $trade, self::TRADES)) $trade = null;
        if ($trade === '') $trade = null;

        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->query('to')   ?: now()->toDateString();

        $base = fn () => $this->scoped($user, $from, $to, $trade);

        $summary = (clone $base())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(pr.amount),0) as revenue,
                         COUNT(DISTINCT pr.order_id) as orders, COUNT(DISTINCT o.vendor_id) as vendors')
            ->first();

        // 소매/도매 비교 (필터와 무관하게 항상 보여준다)
        $byTrade = $this->scoped($user, $from, $to, null)
            ->selectRaw("v.trade_type, COUNT(*) as cnt, COALESCE(SUM(pr.amount),0) as revenue")
            ->groupBy('v.trade_type')->get()->keyBy('trade_type');

        $rows = match ($view) {
            'daily'     => $this->byDate($base(), '%Y-%m-%d'),
            'monthly'   => $this->byDate($base(), '%Y-%m'),
            'vendor'    => $this->byLabel($base(), 'v.name'),
            'agent'     => $this->byLabel(
                                $base()->leftJoin('users as u', 'u.id', '=', 'o.agent_user_id'),
                                "COALESCE(NULLIF(u.business_name,''), u.name)"),
            'book'      => $this->byBook($user, $from, $to, $trade, 'book'),
            'publisher' => $this->byBook($user, $from, $to, $trade, 'publisher'),
        };

        return view('public.mypage.sales', [
            'user'    => $user,
            'view'    => $view,
            'views'   => self::VIEWS,
            'trade'   => $trade,
            'trades'  => self::TRADES,
            'from'    => $from,
            'to'      => $to,
            'summary' => $summary,
            'byTrade' => $byTrade,
            'rows'    => $rows,
        ]);
    }

    /** 일별·월별 */
    private function byDate($q, string $format)
    {
        return $q->selectRaw("DATE_FORMAT(pr.paid_at, '{$format}') as label,
                              COUNT(*) as cnt,
                              COUNT(DISTINCT pr.order_id) as orders,
                              COALESCE(SUM(pr.amount),0) as revenue")
            ->groupBy('label')->orderByDesc('label')->limit(400)->get();
    }

    /** 거래처별·영업담당자별 */
    private function byLabel($q, string $labelExpr)
    {
        return $q->selectRaw("{$labelExpr} as label,
                              COUNT(*) as cnt,
                              COUNT(DISTINCT pr.order_id) as orders,
                              COALESCE(SUM(pr.amount),0) as revenue")
            ->groupBy('label')->orderByDesc('revenue')->limit(400)->get();
    }

    /**
     * 도서별·출판사별 — 결제는 주문 단위라 도서 구분이 없다.
     * 주문별 결제합계를 그 주문의 도서 금액 비중(line_total)으로 안분한다.
     */
    private function byBook(User $user, string $from, string $to, ?string $trade, string $mode)
    {
        $paidPerOrder = $this->scoped($user, $from, $to, $trade)
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
