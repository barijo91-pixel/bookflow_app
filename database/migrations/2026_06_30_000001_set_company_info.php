<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * 회사(사업자) 정보 운영 반영 — 상호 "이런" 등.
     * 시더는 기존 value를 보존하므로, 실제 회사정보는 이 마이그레이션으로 세팅.
     * (이후 관리자가 사이트설정에서 수정하면 그 값이 유지됨)
     */
    public function up(): void
    {
        $company = [
            'company_name'    => '이런',
            'service_name'    => 'BookSys',
            'representative'  => '전찬주',
            'business_no'     => '603-39-60694',
            'biz_report_no'   => '제 2026-서울양천-0556 호',
            'company_address' => '서울시 양천구 목동중앙북로 7 2층 209-M201호(목동, 상가)',
            'company_phone'   => '1688-7561',
            'company_email'   => 'barijo@daum.net',
        ];

        foreach ($company as $key => $value) {
            DB::table('site_settings')->where('key', $key)->update([
                'value'      => $value,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // 회사정보 되돌리지 않음
    }
};
