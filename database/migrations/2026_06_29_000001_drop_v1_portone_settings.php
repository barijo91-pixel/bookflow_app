<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * PortOne V1(아임포트) 설정 키 제거 — V2(store_id/channel_key/api_secret)로 전환.
     * 관리자 사이트설정에서 구버전 필드가 중복 노출되지 않도록 정리.
     */
    public function up(): void
    {
        DB::table('site_settings')
            ->whereIn('key', ['portone_imp_uid', 'portone_rest_api_key', 'portone_rest_secret'])
            ->delete();
    }

    public function down(): void
    {
        // V1 키 복원 안 함 (V2 전환)
    }
};
