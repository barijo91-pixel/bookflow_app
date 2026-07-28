<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 결제 채널 표시 토글(카드/카카오) 추가.
 *  - portone_card_enabled / portone_kakao_enabled (boolean, 기본 켜짐)
 *  - 사용자 요청(카카오페이 수수료 이슈)으로, 기존에 카카오 채널키가 설정된 설치에 한해
 *    카카오페이 표시를 즉시 끔(0). 채널키는 보존 → 관리자에서 언제든 다시 켤 수 있음.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $ensure = function (string $key, string $label, string $desc, int $sort) use ($now) {
            if (! DB::table('site_settings')->where('key', $key)->exists()) {
                DB::table('site_settings')->insert([
                    'group'      => 'integration',
                    'key'        => $key,
                    'value'      => '1',
                    'type'       => 'boolean',
                    'label'      => $label,
                    'description'=> $desc,
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        };

        $ensure('portone_card_enabled', '카드 결제 표시',
            '체크 해제 시 결제화면에서 카드결제 숨김 (채널키는 보존)', 133);
        $ensure('portone_kakao_enabled', '카카오페이 표시',
            '체크 해제 시 결제화면에서 카카오페이 숨김 (채널키는 보존)', 135);

        // 카카오 채널키가 이미 설정된 기존 설치에서만 카카오페이 숨김 (신규 설치는 기본 켜짐 유지)
        $kakaoKey = DB::table('site_settings')->where('key', 'portone_channel_kakao')->value('value');
        if (! empty($kakaoKey)) {
            DB::table('site_settings')->where('key', 'portone_kakao_enabled')
                ->update(['value' => '0', 'updated_at' => $now]);
        }

        if (class_exists(\App\Models\SiteSetting::class)) {
            \App\Models\SiteSetting::flush();
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', ['portone_card_enabled', 'portone_kakao_enabled'])->delete();
        if (class_exists(\App\Models\SiteSetting::class)) {
            \App\Models\SiteSetting::flush();
        }
    }
};
