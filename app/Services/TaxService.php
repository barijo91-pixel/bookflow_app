<?php

namespace App\Services;

/**
 * 사입자(영업자) 세무 계산 서비스 — 계획서 8-A장
 *
 * 정책:
 * - 비사업자: 3.3% 원천징수 (소득세 3% + 지방소득세 0.3%)
 * - 개인사업자: 부가세 별도 발급, 종합소득세 신고
 * - 법인: 부가세 별도 발급, 법인세
 */
class TaxService
{
    public const WITHHOLDING_RATE = 0.033; // 3.3%
    public const VAT_RATE         = 0.10;  // 10%

    public const TYPES = [
        'none'               => '비사업자 (N잡·알바)',
        'individual_simple'  => '개인사업자 (간이과세)',
        'individual_general' => '개인사업자 (일반과세)',
        'corporate'          => '법인',
    ];

    /** 사업자 유형별 정산 계산 */
    public static function calc(string $businessType, int $grossCommission): array
    {
        $result = [
            'gross'              => $grossCommission,  // 명목 수수료
            'withholding_tax'    => 0,                 // 원천징수 (3.3%)
            'vat'                => 0,                 // 부가세 (10%, 별도)
            'net'                => 0,                 // 실수령액
            'note'               => '',
        ];

        switch ($businessType) {
            case 'none':
                // 비사업자: 3.3% 원천징수
                $result['withholding_tax'] = (int) round($grossCommission * self::WITHHOLDING_RATE);
                $result['net'] = $grossCommission - $result['withholding_tax'];
                $result['note'] = '3.3% 원천징수 차감 후 실수령액';
                break;

            case 'individual_simple':
                // 개인사업자 간이과세: 부가세 없이 전액. 종소세 별도 신고
                $result['net'] = $grossCommission;
                $result['note'] = '간이과세자 — 전액 수령 후 종합소득세 별도 신고';
                break;

            case 'individual_general':
            case 'corporate':
                // 일반과세자/법인: 부가세 10% 별도. 명목 + 부가세 청구
                $result['vat'] = (int) round($grossCommission * self::VAT_RATE);
                $result['net'] = $grossCommission + $result['vat'];
                $result['note'] = '부가세 10% 별도 수령 (세금계산서 발행)';
                break;
        }

        return $result;
    }

    /**
     * 누적 수수료 기반 단계 안내 — 계획서 8-A-3장
     * @return array{stage:int, label:string, recommendation:string, alert_level:string}
     */
    public static function checkStage(int $annualCommission, string $businessType): array
    {
        // 비사업자만 단계 안내 (사업자는 이미 등록 완료)
        if ($businessType !== 'none') {
            return [
                'stage'          => 0,
                'label'          => '사업자 등록 완료',
                'recommendation' => '세금계산서 발행으로 정상 거래 중',
                'alert_level'    => 'success',
            ];
        }

        if ($annualCommission >= 100_000_000) {
            return [
                'stage'          => 4,
                'label'          => '연 1억 이상',
                'recommendation' => '법인 전환 강력 권장 (세무·법무사 동시 상담)',
                'alert_level'    => 'danger',
            ];
        }
        if ($annualCommission >= 80_000_000) {
            return [
                'stage'          => 3,
                'label'          => '연 8천만원 이상',
                'recommendation' => '일반과세자 전환 의무 — 부가세 10% 별도 징수·신고 필요',
                'alert_level'    => 'danger',
            ];
        }
        if ($annualCommission >= 50_000_000) {
            return [
                'stage'          => 2,
                'label'          => '연 5천만원 이상',
                'recommendation' => '세무사 고용 또는 기장 위탁 권장 (종합소득세 환급 가능)',
                'alert_level'    => 'warning',
            ];
        }
        if ($annualCommission >= 30_000_000) {
            return [
                'stage'          => 1,
                'label'          => '연 3천만원 이상',
                'recommendation' => '개인사업자 등록 검토 (3.3% vs 비용 처리 효익 비교)',
                'alert_level'    => 'warning',
            ];
        }
        if ($annualCommission >= 25_000_000) {
            return [
                'stage'          => 1,
                'label'          => '연 2,500만원 도달',
                'recommendation' => '개인사업자 전환 시점이 다가오고 있습니다 (3천만원 권장)',
                'alert_level'    => 'info',
            ];
        }

        return [
            'stage'          => 0,
            'label'          => '비사업자 상태',
            'recommendation' => '3.3% 원천징수로 자동 정산 중',
            'alert_level'    => 'success',
        ];
    }
}
