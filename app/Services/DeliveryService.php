<?php

namespace App\Services;

/**
 * 배송비 계산 서비스 — 계획서 6-3장
 *
 * 정책:
 * - 1권 주문: 4,000원 구매자 부담 (총판 권당 마진 100% 보존)
 * - 2권 이상: 무료
 * - 클래스 묶음(B2C, 학부모 일괄 결제): 금액 무관 무료 특례
 * - 직접배송: 화물·용달 실비 (총판이 사입자에게 별도 청구)
 */
class DeliveryService
{
    public const PARCEL_FEE_SINGLE_BOOK = 4000; // 1권 주문 시 구매자 부담 배송비

    /**
     * 택배 배송비 계산
     *
     * @param int  $itemCount  주문 수량 (권수)
     * @param bool $isClassBundle  클래스 묶음 결제 여부 (B2C 학부모 일괄)
     * @return array{fee: int, payer: string, reason: string}
     */
    public static function calcParcelFee(int $itemCount, bool $isClassBundle = false): array
    {
        // 클래스 묶음은 무조건 무료 (전환율 향상 목적)
        if ($isClassBundle) {
            return [
                'fee'    => 0,
                'payer'  => 'free',
                'reason' => '클래스 묶음 특례 — 무료 배송',
            ];
        }

        // 1권 주문: 구매자 부담
        if ($itemCount <= 1) {
            return [
                'fee'    => self::PARCEL_FEE_SINGLE_BOOK,
                'payer'  => 'buyer',
                'reason' => '1권 주문 — 구매자 배송비 부담 (총판 마진 보존)',
            ];
        }

        // 2권+: 무료
        return [
            'fee'    => 0,
            'payer'  => 'free',
            'reason' => '2권 이상 — 무료 배송',
        ];
    }

    /**
     * 직접배송비 추정 (총판이 사입자에게 별도 청구)
     * 실제 금액은 화물·용달 업체 견적 기준. 본 메소드는 가이드 금액만 제공.
     *
     * @param int  $itemCount        수량
     * @param int  $totalAmount      주문 금액
     * @param bool $isNearby         근거리(10km 이내) 여부
     * @param bool $isBundleSameDay  당일 묶음 배송 여부
     */
    public static function estimateDirectFee(int $itemCount, int $totalAmount, bool $isNearby = false, bool $isBundleSameDay = false): array
    {
        // 기준: 화물·용달 평균 30,000원 (가이드)
        $baseFee = 30000;
        $discount = 0;
        $notes = [];

        if ($isNearby) {
            $discount += 10000;
            $notes[] = '근거리 10km 이내 -10,000원';
        }
        if ($isBundleSameDay) {
            $discount += 5000;
            $notes[] = '당일 묶음 배송 -5,000원';
        }

        $fee = max(15000, $baseFee - $discount); // 최소 15,000원

        return [
            'fee'      => $fee,
            'base_fee' => $baseFee,
            'discount' => $discount,
            'notes'    => $notes,
            'reason'   => '직접배송 (총판이 사입자에게 별도 청구) — 가이드 금액',
        ];
    }
}
