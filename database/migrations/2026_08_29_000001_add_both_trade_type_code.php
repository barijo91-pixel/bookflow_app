<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 거래유형에 '도·소매'(both) 추가.
 *
 * 교재에 따라 도매·소매를 섞는 학원이 있다는 영업 현장 의견 반영.
 * vendors.trade_type 은 varchar(20) 이라 스키마 변경은 없고 코드값만 넣는다.
 * 시더에도 같은 내용이 있지만 운영은 db:seed 를 다시 돌리지 않아 여기서 처리.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('codes')->updateOrInsert(
            ['group_code' => 'vendor_trade_type', 'code' => 'both'],
            [
                'name'       => '도·소매',
                'sort_order' => 30,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // 이미 both 로 지정된 학원이 있으면 코드만 지우면 화면이 깨진다 — 쓰이는 중이면 남긴다
        $inUse = DB::table('vendors')->where('trade_type', 'both')->exists();
        if (! $inUse) {
            DB::table('codes')->where('group_code', 'vendor_trade_type')->where('code', 'both')->delete();
        }
    }
};
