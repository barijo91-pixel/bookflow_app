<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * 마진표 기준 정정: 도매학원의 기존 기본 할인율(10%)을 30%로 일괄 조정.
     * (도매학원 = 정가의 70% 매입 = 할인율 30%)
     * 소매학원은 소개료 모델이라 할인율을 쓰지 않으므로 건드리지 않는다.
     * 이미 다른 값으로 직접 설정한 학원(10%가 아닌 값)은 보존한다.
     */
    public function up(): void
    {
        DB::table('agent_vendor_discounts')
            ->whereIn('vendor_id', function ($q) {
                $q->select('id')->from('vendors')->where('trade_type', 'wholesale');
            })
            ->where('discount_rate', 10)
            ->update(['discount_rate' => 30, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // 데이터 보정 마이그레이션 — 되돌리지 않음
    }
};
