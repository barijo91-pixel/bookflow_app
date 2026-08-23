<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 반품에 "대상 학부모 결제"를 지정한다.
 *
 * 소매 주문은 학부모 여러 명이 각자 결제한다. A 학생이 그만둬서 반품하는데
 * 환불이 아무 결제에서나 나가면 B 학부모 카드가 취소되는 사고가 난다.
 * 지정된 결제가 있으면 그 건에서 우선 환불하고, 모자랄 때만 다른 건으로 넘어간다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_request_id')->nullable()->after('vendor_id')
                  ->comment('환불 대상 학부모 결제 (null = 지정 없음, 순서대로)');
            $table->index('payment_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropIndex(['payment_request_id']);
            $table->dropColumn('payment_request_id');
        });
    }
};
