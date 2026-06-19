<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\SettlementRecord;
use App\Models\User;
use App\Services\SettlementService;
use App\Services\TaxService;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    /**
     * 정산 시뮬레이터 — 도서/수량/할인율/분배비율 입력하면
     * 단계별 분배 결과를 표시 (PG 실연동 전 계산 검증용)
     */
    public function simulator(Request $request)
    {
        $unitPrice    = (int) $request->input('unit_price', 16000);
        $qty          = (int) $request->input('qty', 30);
        $discountRate = (float) $request->input('discount_rate', 30);
        $splitRatio   = $request->input('split_ratio', '6:4');
        $businessType = $request->input('business_type', 'none');
        $shippingFee  = (int) $request->input('shipping_fee', 0);
        $bookId       = $request->input('book_id');

        if (!array_key_exists($splitRatio, SettlementService::SPLIT_SCENARIOS)) {
            $splitRatio = '6:4';
        }
        if (!array_key_exists($businessType, TaxService::TYPES)) {
            $businessType = 'none';
        }

        // 도서 검색용 데이터
        $books = Book::orderBy('title')->limit(100)->get(['id', 'title', 'price']);
        if ($bookId) {
            $book = Book::find($bookId);
            if ($book) {
                $unitPrice = (int) $book->price;
            }
        }

        // B2B (학원 도매) 정산
        $b2b = SettlementService::calcB2B($unitPrice, $qty, $discountRate, $splitRatio);
        $b2bAgentTax = TaxService::calc($businessType, max(0, $b2b['agent_margin']));

        // B2C (학부모 결제) 정산
        $parcelInfo = DeliveryService::calcParcelFee($qty);
        $b2cShippingFee = $shippingFee > 0 ? $shippingFee : ($parcelInfo['payer'] === 'buyer' ? $parcelInfo['fee'] : 0);
        $b2c = SettlementService::calcB2C($unitPrice, $qty, $b2cShippingFee, $splitRatio);
        $b2cAgentTax = TaxService::calc($businessType, max(0, $b2c['agent_net']));

        // 분배 시나리오 비교 (3개 시나리오 모두)
        $scenarios = [];
        foreach (array_keys(SettlementService::SPLIT_SCENARIOS) as $ratio) {
            $scenarios[$ratio] = [
                'b2b' => SettlementService::calcB2B($unitPrice, $qty, $discountRate, $ratio),
                'b2c' => SettlementService::calcB2C($unitPrice, $qty, $b2cShippingFee, $ratio),
            ];
        }

        return view('admin.settlement.simulator', [
            'inputs' => [
                'unit_price'    => $unitPrice,
                'qty'           => $qty,
                'discount_rate' => $discountRate,
                'split_ratio'   => $splitRatio,
                'business_type' => $businessType,
                'shipping_fee'  => $b2cShippingFee,
                'book_id'       => $bookId,
            ],
            'b2b'         => $b2b,
            'b2c'         => $b2c,
            'b2bAgentTax' => $b2bAgentTax,
            'b2cAgentTax' => $b2cAgentTax,
            'scenarios'   => $scenarios,
            'parcelInfo'  => $parcelInfo,
            'splitOptions'=> SettlementService::SPLIT_SCENARIOS,
            'businessTypes' => TaxService::TYPES,
            'books'       => $books,
        ]);
    }

    /**
     * 실제 주문에 대한 정산 미리보기 (선택 주문 기반)
     */
    public function orderPreview(int $orderId)
    {
        $order = Order::with(['items.book', 'vendor', 'agent'])->findOrFail($orderId);
        $splitRatio = '6:4';

        $itemBreakdown = [];
        $totalB2b = [
            'gross' => 0, 'academy_paid' => 0, 'publisher_cost' => 0,
            'dist_margin' => 0, 'agent_margin' => 0,
        ];

        foreach ($order->items as $item) {
            // 정가는 book.price 우선, 없으면 단가에서 역산
            $bookPrice = (int) ($item->book?->price ?? $item->unit_price);
            $itemDiscount = (float) ($item->discount_rate ?? 30);

            $b2b = SettlementService::calcB2B(
                $bookPrice,
                (int) $item->qty,
                $itemDiscount,
                $splitRatio
            );
            $itemBreakdown[] = ['item' => $item, 'b2b' => $b2b, 'book_price' => $bookPrice];
            foreach (array_keys($totalB2b) as $k) {
                $totalB2b[$k] += $b2b[$k];
            }
        }

        $businessType = $order->agent?->business_type ?? 'none';
        $agentTax = TaxService::calc($businessType, max(0, $totalB2b['agent_margin']));

        return view('admin.settlement.order_preview', [
            'order' => $order,
            'itemBreakdown' => $itemBreakdown,
            'totalB2b' => $totalB2b,
            'agentTax' => $agentTax,
            'businessType' => $businessType,
            'splitRatio' => $splitRatio,
        ]);
    }

    /**
     * 정산 레코드 목록 (관리자 — 전체 조회)
     */
    public function records(Request $request)
    {
        $status      = $request->input('status');
        $agentId     = $request->input('agent_id');
        $vendorId    = $request->input('vendor_id');
        $from        = $request->input('from');
        $to          = $request->input('to');

        $q = SettlementRecord::with(['vendor', 'agent', 'distributor', 'paymentRequest'])
            ->orderByDesc('id');

        if ($status)   $q->where('status', $status);
        if ($agentId)  $q->where('agent_user_id', $agentId);
        if ($vendorId) $q->where('vendor_id', $vendorId);
        if ($from)     $q->where('computed_at', '>=', $from . ' 00:00:00');
        if ($to)       $q->where('computed_at', '<=', $to . ' 23:59:59');

        $records = $q->paginate(20)->withQueryString();

        // 합계
        $totals = SettlementRecord::query()
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($agentId, fn($q) => $q->where('agent_user_id', $agentId))
            ->when($vendorId, fn($q) => $q->where('vendor_id', $vendorId))
            ->when($from, fn($q) => $q->where('computed_at', '>=', $from . ' 00:00:00'))
            ->when($to, fn($q) => $q->where('computed_at', '<=', $to . ' 23:59:59'))
            ->selectRaw('
                COALESCE(SUM(parent_paid),0) as parent_paid,
                COALESCE(SUM(agent_net),0) as agent_net,
                COALESCE(SUM(agent_payout),0) as agent_payout,
                COALESCE(SUM(dist_net),0) as dist_net,
                COALESCE(SUM(pg_fee),0) as pg_fee,
                COALESCE(SUM(booksys_fee),0) as booksys_fee
            ')->first();

        $agents = User::where('role_code', 'agent')->orderBy('name')->get(['id', 'name']);

        return view('admin.settlement.records', compact('records', 'totals', 'agents', 'status', 'from', 'to'));
    }

    /**
     * 정산 레코드 상세 + 지급 처리
     */
    public function recordShow(int $id)
    {
        $record = SettlementRecord::with(['vendor', 'agent', 'distributor', 'order.items.book', 'paymentRequest'])
            ->findOrFail($id);
        return view('admin.settlement.record_show', compact('record'));
    }

    public function recordMarkPaid(Request $request, int $id)
    {
        $record = SettlementRecord::findOrFail($id);
        if ($record->status === 'paid_out') {
            return back()->with('info', '이미 지급 처리된 정산입니다.');
        }
        $record->update([
            'status'      => 'paid_out',
            'paid_out_at' => now(),
            'paid_out_by' => auth()->id(),
            'memo'        => trim(($record->memo ?? '') . "\n[지급] " . now()->format('Y-m-d H:i') . ' by ' . auth()->user()?->name),
        ]);
        return back()->with('success', '사입자 지급 처리 완료');
    }
}
