<?php

namespace App\Services;

/**
 * 학원 거래구분(vendors.trade_type) 판정 — 단일 출처.
 *
 *  - retail    소매    : 학부모가 개별 결제·개별 배송
 *  - wholesale 도매    : 학원이 사서 학생에게 전달. 항상 학원 일괄 배송
 *  - both      도·소매 : 교재에 따라 둘 다. 주문마다 배송지를 고른다 (2026-08-29 추가)
 *
 * 'both' 의 핵심 규칙: **배송지가 곧 거래 성격**이다.
 *  - 학원 일괄 배송을 고르면 → 도매 주문 (학원이 결제, 학급 지정 불필요)
 *  - 학부모 개별 배송을 고르면 → 소매 주문 (학부모 결제요청, 학급 필요)
 *
 * 그래서 코드 곳곳의 `trade_type === 'wholesale'` 비교를 그대로 두면 안 된다.
 * "이 주문을 도매로 볼지"는 반드시 orderIsWholesale() 로 판정할 것.
 */
class TradeService
{
    public const TYPES = [
        'retail'    => '소매',
        'wholesale' => '도매',
        'both'      => '도·소매',
    ];

    /** 저장 전 정규화 — 모르는 값은 소매로 */
    public static function normalize(?string $type): string
    {
        return isset(self::TYPES[$type]) ? $type : 'retail';
    }

    public static function label(?string $type): string
    {
        return self::TYPES[self::normalize($type)];
    }

    /** 소매(학부모 개별 결제·배송) 거래를 하는가 — 학급/학생 관리가 필요한가 */
    public static function allowsRetail(?string $type): bool
    {
        return self::normalize($type) !== 'wholesale';
    }

    /** 도매(학원 일괄) 거래를 하는가 */
    public static function allowsWholesale(?string $type): bool
    {
        return in_array(self::normalize($type), ['wholesale', 'both'], true);
    }

    /** 주문마다 배송지를 고를 수 있는가 — 도매는 항상 학원이라 고를 게 없다 */
    public static function shipChoosable(?string $type): bool
    {
        return self::normalize($type) !== 'wholesale';
    }

    /**
     * 이 주문을 도매 거래로 볼 것인가.
     * 도매 학원은 항상 도매. 도·소매 학원은 학원 일괄 배송일 때만 도매.
     *
     * 도매로 판정되면: 학원이 직접 결제하고, 학급을 지정하지 않아도 된다.
     */
    public static function orderIsWholesale(?string $tradeType, ?string $shipToType): bool
    {
        $t = self::normalize($tradeType);
        if ($t === 'wholesale') return true;
        if ($t === 'both')      return ($shipToType ?? 'parent') === 'vendor';
        return false;
    }

    /** 학원 설정의 기본 배송지 — 도매는 학원 고정, 나머지는 저장된 값 */
    public static function defaultShipTo(?string $tradeType, ?string $stored): string
    {
        if (self::normalize($tradeType) === 'wholesale') return 'vendor';
        return ($stored ?? 'parent') === 'vendor' ? 'vendor' : 'parent';
    }
    /**
     * 도매 주문에 별도 할인율을 쓰는가 — 도·소매 학원에서만.
     * 순수 도매 학원은 discount_rate 자체가 도매율이라 나눌 필요가 없다.
     */
    public static function usesSplitRate(?string $tradeType): bool
    {
        return self::normalize($tradeType) === 'both';
    }

    /**
     * 이 주문에 적용할 학원 할인율.
     *
     * @param float      $baseRate      agent_vendor_discounts.discount_rate (소매/기본)
     * @param float|null $wholesaleRate agent_vendor_discounts.wholesale_discount_rate (도매용, 없으면 null)
     */
    public static function effectiveRate(float $baseRate, ?float $wholesaleRate, ?string $tradeType, ?string $shipToType): float
    {
        if (self::usesSplitRate($tradeType)
            && $wholesaleRate !== null
            && self::orderIsWholesale($tradeType, $shipToType)) {
            return $wholesaleRate;
        }
        return $baseRate;
    }
}
