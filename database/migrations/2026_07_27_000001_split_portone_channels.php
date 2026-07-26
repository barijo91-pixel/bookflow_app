<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PortOne 단일 채널 → 카드/카카오 2채널 분리.
 *  - portone_channel_card / portone_channel_kakao 설정을 보장(생성)
 *  - 기존 단일 채널값(portone_v2_channel_key)을 결제수단(portone_pay_method)에 맞춰 이관
 *      · EASY_PAY  → portone_channel_kakao
 *      · 그 외/CARD → portone_channel_card
 *  - 사용하지 않게 된 옛 설정(portone_v2_channel_key, portone_pay_method) 제거
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
                    'value'      => '',
                    'type'       => 'text',
                    'label'      => $label,
                    'description'=> $desc,
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        };

        $ensure('portone_channel_card', 'PortOne 채널 키 (카드/이니시스)',
            '카드 PG 채널키 channel-key-xxxx. 비우면 카드결제 숨김', 132);
        $ensure('portone_channel_kakao', 'PortOne 채널 키 (카카오페이)',
            '카카오페이 채널키 channel-key-xxxx. 비우면 카카오페이 숨김', 134);

        // 기존 단일 채널값 이관
        $legacyKey    = DB::table('site_settings')->where('key', 'portone_v2_channel_key')->value('value');
        $legacyMethod = DB::table('site_settings')->where('key', 'portone_pay_method')->value('value');

        if (! empty($legacyKey)) {
            $target = strtoupper((string) $legacyMethod) === 'EASY_PAY'
                ? 'portone_channel_kakao'
                : 'portone_channel_card';

            $current = DB::table('site_settings')->where('key', $target)->value('value');
            if (empty($current)) {
                DB::table('site_settings')->where('key', $target)
                    ->update(['value' => $legacyKey, 'updated_at' => $now]);
            }
        }

        // 옛 설정 제거 (값은 위에서 이관됨)
        DB::table('site_settings')->whereIn('key', ['portone_v2_channel_key', 'portone_pay_method'])->delete();

        if (class_exists(\App\Models\SiteSetting::class)) {
            \App\Models\SiteSetting::flush();
        }
    }

    public function down(): void
    {
        // 되돌리기: 단일 채널 설정 복원 (카카오 우선), 신규 설정 제거
        $now = now();
        $kakao = DB::table('site_settings')->where('key', 'portone_channel_kakao')->value('value');
        $card  = DB::table('site_settings')->where('key', 'portone_channel_card')->value('value');

        $key    = $kakao ?: $card;
        $method = $kakao ? 'EASY_PAY' : 'CARD';

        if (! DB::table('site_settings')->where('key', 'portone_v2_channel_key')->exists()) {
            DB::table('site_settings')->insert([
                'group' => 'integration', 'key' => 'portone_v2_channel_key', 'value' => (string) $key,
                'type' => 'text', 'label' => 'PortOne 채널 키 (Channel Key)', 'description' => null,
                'sort_order' => 130, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('site_settings')->where('key', 'portone_pay_method')->exists()) {
            DB::table('site_settings')->insert([
                'group' => 'integration', 'key' => 'portone_pay_method', 'value' => $method,
                'type' => 'select', 'label' => 'PG 결제수단', 'description' => null,
                'sort_order' => 145, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('site_settings')->whereIn('key', ['portone_channel_card', 'portone_channel_kakao'])->delete();

        if (class_exists(\App\Models\SiteSetting::class)) {
            \App\Models\SiteSetting::flush();
        }
    }
};
