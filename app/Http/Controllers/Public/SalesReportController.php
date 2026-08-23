<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DistributorScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 매출 조회 — 결제액(settlement_records.parent_paid) 기준.
 *
 * "정산 내역"은 누가 얼마 받는지(배분)를 보는 화면이고, 여기는 얼마 팔렸는지를 본다.
 * 주문했지만 아직 결제되지 않은 건은 매출로 잡지 않는다(형아 결정 2026-08-22).
 *
 * 도서별·출판사별은 정산 레코드에 도서 구분이 없어(주문 단위 결제)
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

    private function authorize(): User
    {
        $user = Auth::user();
        if (! $user || ! in_array($user->role_code, ['agent', 'distributor'], true)) {
            abort(403, '영업자 또는 총판만 접근 가능합니다.');
        }
        return $user;
    }

    /** 역할별 정산 레코드 스코프 */
    private function scoped(User $user)
    {
        $q = DB::table('settlement_records as s');
        if ($user->role_code === 'agent') {
            $q->where('s.agent_user_id', $user->id);
        } else {
            $q->where('s.distributor_user_id', $user->id);
        }
        return $q;
    }

    public function index(Request $request)
    {
        $user = $this->authorize();

        $view = $request->query('view', 'daily');
        if (! array_key_exists($view, self::VIEWS)) $view = 'daily';
        // 영업자는 본인 하나뿐이라 담당자별이 의미 없다
        if ($view === 'agent' && $user->role_code === 'agent') $view = 'daily';

        // 기간 — 기본 이번 달
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->query('to')   ?: now()->toDateString();

        $base = fn () => $this->scoped($user)
            ->whereDate('s.computed_at', '>=', $from)
            ->whereDate('s.computed_at', '<=', $to);

        // 상단 요약
        $summary = (clone $base())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(s.parent_paid),0) as revenue,
                         COUNT(DISTINCT s.order_id) as orders, COUNT(DISTINCT s.vendor_id) as vendors')
            ->first();

        $rows = match ($view) {
            'daily'     => $this->byDate($base(), '%Y-%m-%d'),
            'monthly'   => $this->byDate($base(), '%Y-%m'),
            'vendor'    => $this->byJoin($base(), 'vendors', 'v', 's.vendor_id', 'v.name'),
            'agent'     => $this->byJoin($base(), 'users', 'u', 's.agent_user_id',
                            DB::raw("COALESCE(NULLIF(u.business_name,''), u.name)")),
            'book'      => $this->byBook($user, $from, $to, 'book'),
            'publisher' => $this->byBook($user, $from, $to, 'publisher'),
        };

        return view('public.mypage.sales', [
            'user'      => $user,
            'view'      => $view,
            'views'     => self::VIEWS,
            'from'      => $from,
            'to'        => $to,
            'summary'   => $summary,
            'rows'      => $rows,
        ]);
    }

    /** 일별·월별 */
    private function byDate($q, string $format)
    {
        return $q->selectRaw("DATE_FORMAT(s.computed_at, '{$format}') as label,
                              COUNT(*) as cnt,
                              COUNT(DISTINCT s.order_id) as orders,
                              COALESCE(SUM(s.parent_paid),0) as revenue")
            ->groupBy('label')
            ->orderByDesc('label')
            ->limit(400)
            ->get();
    }

    /** 거래처별·영업담당자별 */
    private function byJoin($q, string $table, string $alias, string $fk, $nameExpr)
    {
        return $q->leftJoin("{$table} as {$alias}", "{$alias}.id", '=', $fk)
            ->selectRaw((is_string($nameExpr) ? $nameExpr : $nameExpr->getValue(DB::connection()->getQueryGrammar()))
                . ' as label,
                   COUNT(*) as cnt,
                   COUNT(DISTINCT s.order_id) as orders,
                   COALESCE(SUM(s.parent_paid),0) as revenue')
            ->groupBy('label')
            ->orderByDesc('revenue')
            ->limit(400)
            ->get();
    }

    /**
     * 도서별·출판사별 — 정산 레코드엔 도서 구분이 없다.
     * 주문별 결제합계를 그 주문의 도서 금액 비중(line_total)으로 안분한다.
     */
    private function byBook(User $user, string $from, string $to, string $mode)
    {
        // 주문별 결제 합계
        $paidPerOrder = $this->scoped($user)
            ->whereDate('s.computed_at', '>=', $from)
            ->whereDate('s.computed_at', '<=', $to)
            ->selectRaw('s.order_id, SUM(s.parent_paid) as paid')
            ->groupBy('s.order_id');

        // 주문별 도서 금액 합계 (안분 분모)
        $orderTotal = DB::table('order_items')
            ->selectRaw('order_id, SUM(line_total) as total')
            ->groupBy('order_id');

        $label = $mode === 'book'
            ? "COALESCE(b.title, oi.title_snapshot)"
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
            ->groupBy('label')
            ->orderByDesc('revenue')
            ->limit(400)
            ->get();
    }
}
