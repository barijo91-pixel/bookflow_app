<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 직접배송 정보 컬럼 추가 — 계획서 6-2장
 *
 * 흐름:
 * 1. 영업자가 [직접배송 신청] 클릭 → delivery_type='direct', direct_requested_at 기록
 * 2. 총판이 화물·용달 배차 → driver_name/phone/vehicle_no 입력
 * 3. 사입자·학원 앱에 기사 정보 표시
 * 4. 배송비는 총판이 사입자에게 별도 청구 (delivery_fee)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            // 직접배송용 기사 정보
            $table->string('driver_name', 50)->nullable()->after('tracking_no')
                ->comment('직접배송 기사 이름');
            $table->string('driver_phone', 20)->nullable()->after('driver_name')
                ->comment('직접배송 기사 연락처');
            $table->string('vehicle_no', 20)->nullable()->after('driver_phone')
                ->comment('직접배송 차량번호 (선택)');
            // 배송비 (총판 → 사입자 별도 청구)
            $table->integer('delivery_fee')->default(0)->after('vehicle_no')
                ->comment('직접배송비 (원, 총판이 사입자에게 별도 청구)');
            // 타임스탬프
            $table->timestamp('direct_requested_at')->nullable()->after('shipped_at')
                ->comment('영업자가 직접배송 신청한 시각');
            $table->timestamp('dispatched_at')->nullable()->after('direct_requested_at')
                ->comment('총판이 배차 완료한 시각');
        });

        // orders 테이블: 영업자가 직접배송 신청 메모 가능
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_memo', 500)->nullable()->after('delivery_type')
                ->comment('영업자 배송 요청 메모');
        });
    }

    public function down(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->dropColumn(['driver_name', 'driver_phone', 'vehicle_no', 'delivery_fee', 'direct_requested_at', 'dispatched_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_memo');
        });
    }
};
