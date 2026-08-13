<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주문 배송지 유형 추가
 *  - parent : 학부모 개별 배송 (소매 기본)
 *  - vendor : 학원 일괄 배송 (학원이 받아서 학생에게 전달)
 * 도매(wholesale) 주문은 원래 학원 수령이므로 vendor 로 채운다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ship_to_type', 10)->default('parent')->after('class_id')
                ->comment('배송지 유형: parent=학부모 개별, vendor=학원 일괄');
        });

        // 기존 도매 주문은 학원 수령으로 정정 (소매는 기본값 parent 유지)
        \Illuminate\Support\Facades\DB::table('orders')
            ->whereIn('vendor_id', function ($q) {
                $q->select('id')->from('vendors')->where('trade_type', 'wholesale');
            })
            ->update(['ship_to_type' => 'vendor']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ship_to_type');
        });
    }
};
