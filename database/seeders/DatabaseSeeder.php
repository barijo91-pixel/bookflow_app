<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // 1. 코드 테이블 (다른 시드의 FK 참조 대상)
            CodeTableSeeder::class,
            // 2. 지역 (시도/시군구)
            RegionSeeder::class,
            // 3. 출판사
            PublisherSeeder::class,
            // 4. 데모 계정 + 거래처 + 관계 + 할인율
            DemoAccountSeeder::class,
            // 5. 데모 도서 + 재고
            DemoBookSeeder::class,
            // 5-1. 데모 주문
            DemoOrderSeeder::class,
            // 6. 알림 템플릿
            NotificationTemplateSeeder::class,
            // 7. 사이트 설정
            SiteSettingSeeder::class,
        ]);
    }
}
