<?php

namespace App\Services;

/**
 * 정산 분배 계산 서비스 — 계획서 5장 (마진 구조), 7장 (정산 흐름)
 *
 * 유통 단계별 공급율 (정가 기준):
 *  - 출판사 → 총판:       55% (총판 매입가)
 *  - 총판 → 사입자(도도매): 63% (총판 단계 마진 8%p)
 *  - 사입자 → 학원(도매):  70% (사입자 단계 마진 7%p)
 *  - B2C 학부모(소매):    90% (도서정가제 10% 할인)
 *  - 전체 마진 풀:         15%p (출판사→학원)
 *
 * 분배 비율 (8%p 도도매 마진 풀 기준):
 *  - 7:3 (총판 유리)
 *  - 6:4 (현실적 균형, 기본값)
 *  - 5:5 (사입자 모집 유리)
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
     * @param int $unitPrice     정가
     * @param int $qty           수량
     * @param float $discountRate 학원 할인율 % (0~30 등)
     * @param string $splitRatio  '7:3' | '6:4' | '5:5'
     */
    public static function calcB2B(int $unitPrice, int $qty, float $discountRate = 30.0, string $splitRatio = '6:4'): array
    {
        $split = self::SPLIT_SCENARIOS[$splitRatio] ?? self::SPLIT_SCENARIOS['6:4'];

        $gross         = $unitPrice * $qty;                                  // 정가 합계
        $academyPaid   = (int) round($gross * (1 - $discountRate / 100));    // 학원이 실제 결제 (할인 적용)
        $publisherCost = (int) round($gross * self::RATE_PUBLISHER_TO_DIST); // 총판 매입 원가 (55%)
        $distToAgent   = (int) round($gross * self::RATE_DIST_TO_AGENT);     // 총판 → 사입자 공급가 (63%)
        $agentToAcademy= (int) round($gross * self::RATE_AGENT_TO_ACADEMY);  // 사입자 → 학원 공급가 (70%)

        // 마진 분배 (8%p 도도매 마진을 분배 비율로 나눔)
        $totalDistAgentPool = $distToAgent - $publisherCost;                  // 8%p (정가의 8%)
        $distMargin         = (int) round($totalDistAgentPool * $split['dist']);  // 총판 분배
        $agentSplitMargin   = (int) round($totalDistAgentPool * $split['agent']); // 사입자 도도매 분배

        // 사입자 영업 마진 (학원 협상으로 발생 — 70% 기준 vs 실제 할인율)
        $agentNegotiationMargin = $academyPaid - $distToAgent;  // 음수일 수도 (학원 할인율 따라)

        $agentTotalMargin = $agentSplitMargin + $agentNegotiationMargin;

        return [
            'gross'                  => $gross,
            'academy_paid'           => $academyPaid,
            'publisher_cost'         => $publisherCost,
            'dist_to_agent'          => $distToAgent,
            'agent_to_academy'       => $agentToAcademy,
            // 마진 분배
            'pool_dist_agent'        => $totalDistAgentPool,
            'dist_margin'            => $distMargin,
            'agent_split_margin'     => $agentSplitMargin,
            'agent_negotiation'      => $agentNegotiationMargin,
            'agent_total_margin'     => $agentTotalMargin,
            // 비율 정보
            'split_ratio'            => $splitRatio,
            'split_label'            => $split['label'],
            'discount_rate'          => $discountRate,
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

        // 사입자 마진 (도도매 마진 분배 비율 기준)
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
}
