<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 거래처(학원) 거래구분 추가
 *  - retail   : 소매 — 학생 개인별, 학부모 각자 결제, 학부모 개별 배송 (B2C, 현재 흐름)
 *  - wholesale: 도매 — 묶음, 학원 일괄 결제(현매/여신), 학원 일괄 배송 (B2B)
 * 기존 학원은 기본 'retail'.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('trade_type', 20)->default('retail')->after('type_code')
                ->comment('거래구분: retail(소매)/wholesale(도매)');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('trade_type');
        });
    }
};
