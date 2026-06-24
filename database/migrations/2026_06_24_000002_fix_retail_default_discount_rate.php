<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * 소매 학원의 영업자 매핑 할인율(= 학부모 할인율)을 소매 기본 10%로 정정.
     * 시드 데모로 25~35%가 들어가 있던 값을 일괄 10%로 맞춘다.
     * 일회성 보정 — 이후 영업자/관리자가 개별 수정한 값은 그대로 유지된다
     * (정산/시뮬레이터는 항상 agent_vendor_discounts의 현재 값을 읽음).
     */
    public function up(): void
    {
        DB::table('agent_vendor_discounts')
            ->whereIn('vendor_id', function ($q) {
                $q->select('id')->from('vendors')->where('trade_type', 'retail');
            })
            ->update(['discount_rate' => 10, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // 데이터 보정 마이그레이션 — 되돌리지 않음
    }
};
