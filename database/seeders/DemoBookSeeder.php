<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoBookSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $publishers = DB::table('publishers')->pluck('id', 'name')->toArray();
        // 총판 사용자 (있으면 사용, 없으면 재고 시드 건너뜀)
        $distA = DB::table('users')->where('email', 'distA@bookflow.local')->value('id')
              ?? DB::table('users')->where('login_id', 'dist01')->value('id')
              ?? DB::table('users')->where('role_code', 'distributor')->value('id');
        $distB = DB::table('users')->where('email', 'distB@bookflow.local')->value('id')
              ?? DB::table('users')->where('role_code', 'distributor')->orderByDesc('id')->value('id');

        $books = [
            ['9788901234001', 'Bricks Phonics 1', 'Student Book',  'Bricks Phonics', '브릭스', 'english', 'elementary', 12000, ['pre_elem','elem_1'], 'intro'],
            ['9788901234002', 'Bricks Phonics 2', 'Student Book',  'Bricks Phonics', '브릭스', 'english', 'elementary', 12000, ['elem_1','elem_2'], 'intro'],
            ['9788901234003', 'Bricks Reading 50', 'Level 1',       'Bricks Reading','브릭스','english','elementary', 13000, ['elem_2','elem_3'], 'basic'],
            ['9788901234004', 'Reading Town 1', null,                'Reading Town', '리딩타운','english','elementary', 14000, ['elem_3','elem_4'], 'basic'],
            ['9788901234005', 'Reading Town 2', null,                'Reading Town', '리딩타운','english','elementary', 14000, ['elem_4','elem_5'], 'basic'],
            ['9788901234006', '뉴 비기너스 영어 1', '입문',              '뉴 비기너스',   'YBM',  'english','elementary', 11000, ['pre_elem','elem_1'], 'intro'],
            ['9788901234007', '능률 VOCA 입문편', null,                 '능률 VOCA',    '능률교육','english','middle',  13500, ['mid_1'], 'intro'],
            ['9788901234008', '쎈 수학 초3', '상',                       '쎈',           '쎈수학','math','elementary',   15000, ['elem_3'], 'basic'],
            ['9788901234009', '쎈 수학 초3', '하',                       '쎈',           '쎈수학','math','elementary',   15000, ['elem_3'], 'basic'],
            ['9788901234010', '디딤돌 초등 국어 4-1', null,             '디딤돌',         '디딤돌','korean','elementary',  12500, ['elem_4'], 'basic'],
            ['9788901234011', '비상 완자 초등 수학 5-1', null,          '완자',          '비상교육','math','elementary',  13000, ['elem_5'], 'inter'],
            ['9788901234012', '미래엔 초등 사회 6-1', null,             '미래엔 사회',    '미래엔','social','elementary',  12000, ['elem_6'], 'inter'],
            ['9788901234013', 'EBS 중학 영어 1', null,                 'EBS 중학',      '교학사','english','middle',     14000, ['mid_1'], 'basic'],
            ['9788901234014', '천재 중학 국어 2', null,                 '천재 국어',      '천재교육','korean','middle',   13800, ['mid_2'], 'inter'],
            ['9788901234015', '동아 중학 수학 3', null,                 '동아 수학',     '동아출판','math','middle',     14500, ['mid_3'], 'inter'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($books as $i => [$isbn, $title, $subtitle, $series, $pubName, $subject, $school, $price, $grades, $level]) {
            // 이미 ISBN 존재하면 건너뜀
            $existing = DB::table('books')->where('isbn', $isbn)->first();
            if ($existing) {
                $skipped++;
                $bookId = $existing->id;
            } else {
                $bookId = DB::table('books')->insertGetId([
                    'isbn' => $isbn,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'series_name' => $series,
                    'publisher_id' => $publishers[$pubName] ?? null,
                    'subject_code' => $subject,
                    'school_code' => $school,
                    'price' => $price,
                    'default_discount_rate' => 0,
                    'status_code' => 'selling',
                    'source' => 'demo',
                    'pub_date' => now()->subMonths(rand(1, 24))->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $created++;
            }

            // book_targets — 중복 회피 위해 updateOrInsert 사용
            foreach ($grades as $g) {
                DB::table('book_targets')->updateOrInsert(
                    ['book_id' => $bookId, 'target_type' => 'grade', 'code' => $g],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
            DB::table('book_targets')->updateOrInsert(
                ['book_id' => $bookId, 'target_type' => 'level', 'code' => $level],
                ['created_at' => $now, 'updated_at' => $now]
            );
            DB::table('book_targets')->updateOrInsert(
                ['book_id' => $bookId, 'target_type' => 'school', 'code' => $school],
                ['created_at' => $now, 'updated_at' => $now]
            );

            // 총판 재고 (총판 사용자가 있을 때만)
            if ($distA) {
                $stockA = ($i % 3 !== 0) ? rand(20, 100) : 0;
                if ($stockA > 0) {
                    DB::table('book_stocks')->updateOrInsert(
                        ['book_id' => $bookId, 'distributor_user_id' => $distA],
                        ['qty' => $stockA, 'low_stock_threshold' => 5, 'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
            if ($distB && $distB !== $distA) {
                $stockB = ($i % 2 === 0) ? rand(20, 100) : 0;
                if ($stockB > 0) {
                    DB::table('book_stocks')->updateOrInsert(
                        ['book_id' => $bookId, 'distributor_user_id' => $distB],
                        ['qty' => $stockB, 'low_stock_threshold' => 5, 'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }

        $this->command->info("도서 시드 완료: 신규 {$created}건, 기존 스킵 {$skipped}건");
        if (! $distA) {
            $this->command->warn('총판 사용자가 없어 재고는 시드되지 않았습니다.');
        }
    }
}
