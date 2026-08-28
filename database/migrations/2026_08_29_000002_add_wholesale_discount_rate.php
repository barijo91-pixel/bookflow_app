<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 도·소매 학원의 도매 주문용 할인율.
 *
 * 지금까지 학원 할인율은 (영업자 × 학원) 당 하나였다. 도·소매 학원은 같은 교재라도
 * 주문에 따라 도매(학원 매입)와 소매(학부모 결제)가 갈리는데 율이 하나뿐이라
 * 어느 한쪽이 늘 틀린 값이 된다.
 *
 * 기존 discount_rate 는 그대로 **소매/기본 율**로 두고, 도매 주문에만 쓰는 율을 따로 둔다.
 *  - NULL 이면 기존처럼 discount_rate 하나로 동작 → 기존 학원은 영향 없음
 *  - 도·소매(both) 학원에서만 의미가 있다. 순수 도매 학원은 discount_rate 자체가 도매율이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_vendor_discounts', function (Blueprint $table) {
            $table->decimal('wholesale_discount_rate', 5, 2)->nullable()->after('discount_rate');
        });
    }

    public function down(): void
    {
        Schema::table('agent_vendor_discounts', function (Blueprint $table) {
            $table->dropColumn('wholesale_discount_rate');
        });
    }
};
