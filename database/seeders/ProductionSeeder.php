<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 운영 배포용 시더 — 데모 데이터(계정/도서/주문) 없이 필수 마스터만.
 *
 * 사용:
 *   php artisan db:seed --class=ProductionSeeder
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. 코드 테이블 (필수)
            CodeTableSeeder::class,
            // 2. 지역 (시도/시군구)
            RegionSeeder::class,
            // 3. 출판사 (선택 — 빈 상태 운영하려면 주석)
            PublisherSeeder::class,
            // 4. 알림 템플릿 (8개 이벤트)
            NotificationTemplateSeeder::class,
            // 5. 사이트 설정 (회사/연동/SEO/정책/앱)
            SiteSettingSeeder::class,
            // 6. 관리자 계정 1개 (admin@bookflow.local / 1234 — 운영 후 즉시 비밀번호 변경 필수)
            AdminAccountSeeder::class,
        ]);

        $this->command->warn('[운영 시드 완료] 관리자 계정: admin@bookflow.local / 1234 → 즉시 변경하세요.');
    }
}
