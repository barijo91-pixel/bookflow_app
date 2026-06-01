<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 총판(distributor)의 PG/입금 계좌 — 학부모 수금 흐름의 시작점
        // 모든 사용자에 컬럼은 두되 의미는 총판에서만 사용
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_code', 10)->nullable()->after('address_detail');
            $table->string('bank_account', 50)->nullable()->after('bank_code');
            $table->string('bank_holder', 50)->nullable()->after('bank_account');
        });

        // 주문 배송 방식 — 영업자가 confirm 시 선택
        // parcel: 일반 택배 (기본), direct: 영업자 직접 배송 (대형 학원)
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_type', 10)->default('parcel')->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bank_code', 'bank_account', 'bank_holder']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_type');
        });
    }
};
