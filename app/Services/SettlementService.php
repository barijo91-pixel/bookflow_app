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

    /** 학원 소개료 원천징수 (3.3%) — 소매 B2C */
    public const REFERRAL_TAX = 0.033;

    /** 소매 B2C 기본값 (사이트 설정 미입력 시 폴백, % 정수) */
    public const B2C_DEFAULT_PUB_RATE      = 55;  // 출판사→총판 공급율
    public const B2C_DEFAULT_SELL_RATE     = 90;  // 학부모 판매율(도서정가제)
    public const B2C_DEFAULT_REFERRAL_RATE = 20;  // 학원 소개료율(마진풀 대비)

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
     * B2C 학부모 결제(소매) 정산 — 학원 "소개료" 모델
     *
     * 출판사 → 총판 → 학부모(직배송). 학원은 매입하지 않고 학부모를 소개만 하며,
     * 마진풀의 일정 비율을 소개료로 받는다(3.3% 원천징수 후 지급).
     * 배송비는 실비라 분배에서 제외(학부모 결제 총액 표시용으로만 더함).
     *
     *   마진풀  = 학부모 판매가 − 총판 매입가
     *   소개료  = 마진풀 × 소개료율 → 3.3% 원천징수 후 학원 지급
     *   잔여마진 = 마진풀 − 소개료(공제 전) → 총판 : 사입자 분배
     *
     * 공급율·판매율·소개료율은 사이트 설정(정산 그룹)에서 조정. 미지정 시 기본값.
     *
     * @param int $unitPrice    정가
     * @param int $qty          수량
     * @param int $shippingFee  배송비 (실비 — 분배 제외, 결제 총액 표시용)
     * @param string $splitRatio 총판:사입자 분배
     * @param float|null $pubRate      출판사→총판 공급율 (0~1, null=설정값)
     * @param float|null $sellRate     학부모 판매율 (0~1, null=설정값)
     * @param float|null $referralRate 학원 소개료율 (0~1, null=설정값)
     */
    public static function calcB2C(
        int $unitPrice,
        int $qty,
        int $shippingFee = 0,
        string $splitRatio = '6:4',
        ?float $pubRate = null,
        ?float $sellRate = null,
        ?float $referralRate = null
    ): array {
        $split = self::SPLIT_SCENARIOS[$splitRatio] ?? self::SPLIT_SCENARIOS['6:4'];

        // 설정값 (% 정수 저장 → 0~1 변환). 미지정 시 사이트 설정 → 기본값
        $pubRate      = $pubRate      ?? ((float) setting('b2c_pub_rate', (string) self::B2C_DEFAULT_PUB_RATE) / 100);
        $sellRate     = $sellRate     ?? ((float) setting('b2c_sell_rate', (string) self::B2C_DEFAULT_SELL_RATE) / 100);
        $referralRate = $referralRate ?? ((float) setting('b2c_referral_rate', (string) self::B2C_DEFAULT_REFERRAL_RATE) / 100);

        $gross         = $unitPrice * $qty;                       // 정가 합계
        $retailSale    = (int) round($gross * $sellRate);         // 학부모 판매가
        $parentPaid    = $retailSale + $shippingFee;              // 학부모 결제 총액 (배송 별도 실비)
        $publisherCost = (int) round($gross * $pubRate);          // 총판 매입가

        // 마진풀 = 학부모 판매가 − 총판 매입가 (배송 제외)
        $marginPool    = $retailSale - $publisherCost;

        // 학원 소개료 = 마진풀 × 소개료율 → 3.3% 원천징수 후 지급
        $referralGross = (int) round($marginPool * $referralRate);
        $referralNet   = (int) round($referralGross * (1 - self::REFERRAL_TAX));

        // 잔여마진 = 마진풀 − 소개료(공제 전) → 총판 : 사입자 분배
        $remain   = $marginPool - $referralGross;
        $distNet  = (int) round($remain * $split['dist']);
        $agentNet = $remain - $distNet;   // 잔여 = 사입자 (반올림 오차 흡수)

        return [
            'gross'           => $gross,
            'retail_sale'     => $retailSale,
            'parent_paid'     => $parentPaid,
            'shipping_fee'    => $shippingFee,
            'publisher_cost'  => $publisherCost,
            'margin_pool'     => $marginPool,
            // 학원 소개료
            'referral_gross'  => $referralGross,    // 공제 전
            'referral_net'    => $referralNet,      // 3.3% 공제 후 실지급
            'referral_rate'   => $referralRate,
            // 분배
            'remain'          => $remain,
            'dist_net'        => $distNet,
            'agent_net'       => $agentNet,
            'split_ratio'     => $splitRatio,
            'split_label'     => $split['label'],
            // 설정값(표시용)
            'pub_rate'        => $pubRate,
            'sell_rate'       => $sellRate,
            // 호환 키 (createFromPaymentRequest / 기존 화면)
            'agent_margin'    => $agentNet,
            'academy_bonus'   => $referralNet,
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
        $totalReferral = 0;     // 학원 소개료 (3.3% 공제 후 실지급)
        $totalAgentNet = 0;     // 사입자
        $totalDistNet = 0;      // 총판
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
                    'referral'  => $b2c['referral_gross'],
                    'dist_net'  => $b2c['dist_net'],
                    'agent_net' => $b2c['agent_net'],
                ];
                $totalGross += $b2c['gross'];
                $totalRetail += $b2c['retail_sale'];
                $totalPublisherCost += $b2c['publisher_cost'];
                $totalReferral += $b2c['referral_gross']; // 학원 소개료 (공제 전 = 마진풀 차감액)
                $totalAgentNet += $b2c['agent_net'];
                $totalDistNet += $b2c['dist_net'];
            }
            $totalShippingFee = $shippingFee;
        }

        // 학부모 실제 결제 금액 (배송비 포함)
        $parentPaid = (int) $pr->amount;
        // 소매 정산: PG/중계 수수료는 분배에서 제외(실제 PG 연동 시 별도 반영) → 0
        $pgFee = 0;
        $bookSysFee = 0;
        $distNet = $totalDistNet;

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

            'agent_margin'         => $totalAgentNet,
            'agent_net'            => $totalAgentNet,
            'academy_bonus'        => $totalReferral,
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
