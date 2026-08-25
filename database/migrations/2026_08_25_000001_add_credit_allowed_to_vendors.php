<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 여신(외상) 학원 플래그.
 * 켜져 있으면 결제 없이도 영업자가 주문을 수동 확정할 수 있다.
 * 꺼져 있으면 대금 전액 결제 시에만 (자동) 확정된다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('credit_allowed')->default(false)->after('credit_limit')
                  ->comment('여신(외상) 구매 허용 — 결제 없이 확정 가능');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('credit_allowed');
        });
    }
};
