<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PublisherSeeder extends Seeder
{
    public function run(): void
    {
        $publishers = [
            'YBM', '능률교육', '교학사', '천재교육', '비상교육', '동아출판',
            '디딤돌', '쎈수학', '미래엔', '두산동아', '시사영어사', '컴퍼스',
            '브릭스', '튼튼영어', '윤선생영어', '리딩타운', 'eBookLand', '월드컴',
        ];

        foreach ($publishers as $i => $name) {
            DB::table('publishers')->updateOrInsert(
                ['name' => $name],
                [
                    'code' => 'PUB' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                    'sort_order' => ($i + 1) * 10,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
