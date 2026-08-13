<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 학원(거래처)별 기본 배송지 설정
 *  - parent : 학부모 개별 배송 (소매 기본)
 *  - vendor : 학원 일괄 수령 후 학생 전달
 * 주문 화면은 이 값을 기본 선택으로 표시하고, 필요 시 건별 변경 가능.
 * 도매 학원은 항상 학원 수령이므로 vendor 로 채운다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('default_ship_to_type', 10)->default('parent')->after('trade_type')
                ->comment('기본 배송지: parent=학부모 개별, vendor=학원 일괄');
        });

        DB::table('vendors')->where('trade_type', 'wholesale')
            ->update(['default_ship_to_type' => 'vendor']);
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('default_ship_to_type');
        });
    }
};
