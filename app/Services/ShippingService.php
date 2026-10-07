<?php

namespace App\Services;

/**
 * 배송비 정책 — 단일 출처.
 *
 * 2026-10-07 형아 지시: 1권 주문은 4,000원, 2권 이상은 무료.
 * 금액·무료 기준 수량은 사이트 설정으로 바꿀 수 있게 두고, 설정이 없으면 아래 기본값.
 */
class ShippingService
{
    /** 1권 주문 배송비 */
    public const DEFAULT_FEE = 4000;

    /** 이 수량부터 무료 */
    public const DEFAULT_FREE_FROM_QTY = 2;

    public static function fee(): int
    {
        return (int) setting('shipping_fee', self::DEFAULT_FEE);
    }

    public static function freeFromQty(): int
    {
        return max(1, (int) setting('shipping_free_from_qty', self::DEFAULT_FREE_FROM_QTY));
    }

    /** 이 주문(총 권수)의 배송비 */
    public static function feeFor(int $totalQty): int
    {
        if ($totalQty <= 0) return 0;
        return $totalQty >= self::freeFromQty() ? 0 : self::fee();
    }

    /** 화면 안내 문구 — 한 곳에서 만들어 장바구니·주문 화면이 같은 말을 하게 */
    public static function notice(): string
    {
        return number_format(self::freeFromQty()) . '권 이상 무료배송 · '
             . (self::freeFromQty() - 1) . '권은 배송비 ' . number_format(self::fee()) . '원';
    }
}
