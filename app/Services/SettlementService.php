<?php

namespace App\Services;

/**
 * 정산 분배 계산 서비스 — 계획서 5장 (마진 구조), 7장 (정산 흐름)
 *
 * 유통 단계별 공급율 (정가 기준):
 *  - 출판사 → 총판:   55% (총판 매입가)
 *  - 총판 → 사입자:   63% (중간 공급가 — 참고 표시용)
 *  - 사입자 → 학원:   70% (학원 도매가)
 *  - B2C 학부모(소매): 90% (도서정가제 10% 할인)
 *
 * 마진 분배 (도매):
 *  - 전체 마진 = 학원 결제액 − 출판사 매입가(55%)
 *  - 이 전체 마진을 총판 : 사입자 비율로 나눈다 (중간 단계 마진을 따로 쪼개지 않음)
 *  - 7:3 (총판 유리) · 6:4 (현실적 균형, 기본값) · 5:5 (사입자 모집 유리)
 */
class SettlementService
{
    /** 유통 단계별 공급율 */
    public const RATE_PUBLISHER_TO_DIST = 0.55;
    public const RATE_DIST_TO_AGENT     = 0.63;
    public const RATE_AGENT_TO_ACADEMY  = 0.70;
    public const RATE_B2C_RETAIL        = 0.90;

    /** PG 수수료 (보수적 2%) */
    public const PG_FEE_RATE = 0.02;

    /** BookSys B2C 중계수수료 (계획서 12장) */
    public const BOOKSYS_FEE_RATE_B2C = 0.005;

    /** 분배 비율 시나리오 — [총판 비율, 사입자 비율] */
    public const SPLIT_SCENARIOS = [
        '7:3' => ['dist' => 0.7, 'agent' => 0.3, 'label' => '7:3 (총판 유리)'],
        '6:4' => ['dist' => 0.6, 'agent' => 0.4, 'label' => '6:4 (현실적 균형 — 기본)'],
        '5:5' => ['dist' => 0.5, 'agent' => 0.5, 'label' => '5:5 (사입자 모집 유리)'],
    ];

    /**
     * B2B 학원 도매 주문 정산
     *
     * 전체 마진(학원 결제액 − 출판사 매입가 55%)을
     * 총판 : 사입자 비율(6:4 등)로 분배한다. 중간 공급가(63%)는 참고 표시용일 뿐
     * 분배 계산에는 쓰지 않는다.
     *
     * @param int $unitPrice     정가
     * @param int $qty           수량
     * @param float $discountRate 학원 할인율 % (예: 30 → 학원이 정가의 70%에 매입)
     * @param string $splitRatio  '7:3' | '6:4' | '5:5'
     */
    public static function calcB2B(int $unitPrice, int $qty, float $discountRate = 30.0, string $splitRatio = '6:4'): array
    {
        $split = self::SPLIT_SCENARIOS[$splitRatio] ?? self::SPLIT_SCENARIOS['6:4'];

        $gross         = $unitPrice * $qty;                                  // 정가 합계
        $academyPaid   = (int) round($gross * (1 - $discountRate / 100));    // 학원이 실제 결제 (할인 적용)
        $publisherCost = (int) round($gross * self::RATE_PUBLISHER_TO_DIST); // 출판사 매입 원가 (55%)
        $distToAgent   = (int) round($gross * self::RATE_DIST_TO_AGENT);     // 총판 → 사입자 공급가 (63%, 참고 표시용)
        $agentToAcademy= (int) round($gross * self::RATE_AGENT_TO_ACADEMY);  // 사입자 → 학원 공급가 (70%, 참고 표시용)

        // 전체 마진 = 학원 결제액 − 출판사 매입가. 이를 총판:사입자 비율로 분배
        $marginPool  = $academyPaid - $publisherCost;
        $distMargin  = (int) round($marginPool * $split['dist']);
        $agentMargin = $marginPool - $distMargin;   // 잔여 = 사입자 (반올림 오차 흡수)

        return [
            'gross'           => $gross,
            'academy_paid'    => $academyPaid,
            'publisher_cost'  => $publisherCost,
            'dist_to_agent'   => $distToAgent,      // 참고 표시값
            'agent_to_academy'=> $agentToAcademy,   // 참고 표시값
            // 마진 분배
            'margin_pool'     => $marginPool,
            'dist_margin'     => $distMargin,
            'agent_margin'    => $agentMargin,
            // 비율 정보
            'split_ratio'     => $splitRatio,
            'split_label'     => $split['label'],
            'discount_rate'   => $discountRate,
        ];
    }

    /**
     * B2C 학부모 결제 정산 (계획서 7-2장)
     *
     * 수금 주체: 총판 PG 계좌
     * 차감 순서: 출판사 매입(55%) → 배송비 → PG 수수료 → BookSys 중계수수료
     *           → 사입자 마진 → 학원 도매 단가 우대
     *
     * @param int $unitPrice    정가
     * @param int $qty          수량
     * @param int $shippingFee  배송비 (0 = 무료)
     * @param string $splitRatio
     */
    public static function calcB2C(int $unitPrice, int $qty, int $shippingFee = 0, string $splitRatio = '6:4'): array
    {
        $split = self::SPLIT_SCENARIOS[$splitRatio] ?? self::SPLIT_SCENARIOS['6:4'];

        $gross         = $unitPrice * $qty;                                  // 정가
        $retailSale    = (int) round($gross * self::RATE_B2C_RETAIL);        // 학부모 매출 (90%)
        $parentPaid    = $retailSale + $shippingFee;                          // 학부모 결제 총액
        $publisherCost = (int) round($gross * self::RATE_PUBLISHER_TO_DIST); // 출판사 매입 (55%)
        $pgFee         = (int) round($parentPaid * self::PG_FEE_RATE);       // PG 수수료
        $bookSysFee    = (int) round($retailSale * self::BOOKSYS_FEE_RATE_B2C); // BookSys 중계 (매출 0.5%)

        // 전체 마진 풀 (학부모 매출 - 출판사 매입 - 배송비 - PG - BookSys 수수료)
        $netMarginPool = $retailSale - $publisherCost - $pgFee - $bookSysFee;

        // 사입자 마진 (분배 비율 기준) — ※ 소매 로직은 추후 확정 예정
        $distAgentPool      = (int) round($gross * (self::RATE_DIST_TO_AGENT - self::RATE_PUBLISHER_TO_DIST));
        $agentMargin        = (int) round($distAgentPool * $split['agent']);

        // 학원 도매 단가 우대 (B2C 시 학원 간접 혜택 — 도매 70% 기준 차액의 일부)
        // 계획서 기준: 학원에 도매 단가로 우대 처리 → 일종의 "수수료 환급" 효과
        // 시뮬레이션: 사입자 마진의 30% 정도를 학원 우대로 추정 (협상 가능)
        $academyBonus = (int) round($agentMargin * 0.3);
        $agentNet = $agentMargin - $academyBonus;

        // 총판 순이익
        $distNet = $netMarginPool - $agentMargin;

        return [
            'gross'           => $gross,
            'retail_sale'     => $retailSale,
            'shipping_fee'    => $shippingFee,
            'parent_paid'     => $parentPaid,
            'publisher_cost'  => $publisherCost,
            'pg_fee'          => $pgFee,
            'booksys_fee'     => $bookSysFee,
            'net_margin_pool' => $netMarginPool,
            'agent_margin'    => $agentMargin,
            'agent_net'       => $agentNet,
            'academy_bonus'   => $academyBonus,
            'dist_net'        => $distNet,
            'split_ratio'     => $splitRatio,
            'split_label'     => $split['label'],
        ];
    }

    /**
     * 사입자 최종 실수령액 계산 (세무 적용)
     * TaxService와 통합
     *
     * @param int $grossCommission  명목 수수료 (B2B 또는 B2C 마진)
     * @param string $businessType  사입자 사업자 유형
     */
    public static function applyTaxToAgentSettlement(int $grossCommission, string $businessType = 'none'): array
    {
        return TaxService::calc($businessType, $grossCommission);
    }

    /**
     * 학부모 결제 완료 → 정산 레코드 자동 생성
     * (PG 콜백 또는 mock 결제 처리에서 호출)
     *
     * @param \App\Models\PaymentRequest $pr  결제 요청
     * @param string $pgTransactionId         PG 거래 ID (mock 시 'MOCK-XXX')
     * @param string $splitRatio              분배 비율 (기본 6:4)
     * @return \App\Models\SettlementRecord
     */
    public static function createFromPaymentRequest(
        \App\Models\PaymentRequest $pr,
        string $pgTransactionId = '',
        string $splitRatio = '6:4'
    ): \App\Models\SettlementRecord {
        // 주문 정보
        $order = \App\Models\Order::with('items.book')->find($pr->order_id);

        // 항목 합계 → 도서 단위 분배 계산
        $totalGross = 0;
        $totalRetail = 0;
        $totalPublisherCost = 0;
        $totalAgentMargin = 0;
        $totalAgentNet = 0;
        $totalAcademyBonus = 0;
        $totalShippingFee = 0;
        $breakdown = [];

        $shippingFee = 0;
        if ($order) {
            $itemCount = $order->items->sum('qty');
            $parcelInfo = \App\Services\DeliveryService::calcParcelFee($itemCount, true); // 클래스 묶음 = 무료
            $shippingFee = $parcelInfo['fee'];

            foreach ($order->items as $item) {
                $bookPrice = (int) ($item->book?->price ?? $item->unit_price);
                $b2c = self::calcB2C($bookPrice, (int) $item->qty, 0, $splitRatio);
                $breakdown[] = [
                    'book_id'   => $item->book_id,
                    'title'     => $item->title_snapshot ?? $item->book?->title,
                    'qty'       => (int) $item->qty,
                    'gross'     => $b2c['gross'],
                    'retail'    => $b2c['retail_sale'],
                    'agent_net' => $b2c['agent_net'],
                ];
                $totalGross += $b2c['gross'];
                $totalRetail += $b2c['retail_sale'];
                $totalPublisherCost += $b2c['publisher_cost'];
                $totalAgentMargin += $b2c['agent_margin'];
                $totalAgentNet += $b2c['agent_net'];
                $totalAcademyBonus += $b2c['academy_bonus'];
            }
            $totalShippingFee = $shippingFee;
        }

        // 학부모 실제 결제 금액 (배송비 포함)
        $parentPaid = (int) $pr->amount;
        $pgFee = (int) round($parentPaid * self::PG_FEE_RATE);
        $bookSysFee = (int) round($totalRetail * self::BOOKSYS_FEE_RATE_B2C);
        $distNet = $parentPaid - $totalPublisherCost - $pgFee - $bookSysFee - $totalAgentMargin - $totalShippingFee;

        // 사입자 정보 + 세무
        $agent = $order ? \App\Models\User::find($order->agent_user_id) : null;
        $businessType = $agent?->business_type ?? 'none';
        $tax = TaxService::calc($businessType, max(0, $totalAgentNet));

        // 총판 결정: 주문에 라우팅된 총판 사용 (다중 총판 지원)
        // fallback: 영업자의 첫 총판 → 그것도 없으면 첫 distributor
        $distributorId = $order?->distributor_user_id;
        if (! $distributorId && $agent) {
            $distributorId = \Illuminate\Support\Facades\DB::table('user_relations')
                ->where('child_user_id', $agent->id)
                ->where('relation_type', 'distributor_agent')
                ->where('status', 'active')
                ->orderBy('id')->value('parent_user_id');
        }
        $distributor = $distributorId
            ? \App\Models\User::find($distributorId)
            : \App\Models\User::where('role_code', 'distributor')->first();

        return \App\Models\SettlementRecord::create([
            'payment_request_id'   => $pr->id,
            'order_id'             => $pr->order_id,
            'vendor_id'            => $pr->vendor_id,
            'agent_user_id'        => $order?->agent_user_id,
            'distributor_user_id'  => $distributor?->id,

            'gross_amount'         => $totalGross,
            'parent_paid'          => $parentPaid,
            'publisher_cost'       => $totalPublisherCost,
            'pg_fee'               => $pgFee,
            'booksys_fee'          => $bookSysFee,
            'shipping_fee'         => $totalShippingFee,

            'agent_margin'         => $totalAgentMargin,
            'agent_net'            => $totalAgentNet,
            'academy_bonus'        => $totalAcademyBonus,
            'dist_net'             => $distNet,

            'agent_business_type'  => $businessType,
            'agent_withholding_tax'=> (int) $tax['withholding_tax'],
            'agent_vat'            => (int) $tax['vat'],
            'agent_payout'         => (int) $tax['net'],

            'split_ratio'          => $splitRatio,
            'settle_type'          => 'b2c',
            'status'               => 'computed',
            'computed_at'          => now(),
            'pg_transaction_id'    => $pgTransactionId,
            'breakdown_json'       => json_encode($breakdown, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
