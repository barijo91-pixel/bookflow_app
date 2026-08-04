<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 특정 출판사 도서 중 학교(school_code)가 비어있는 건을 지정 코드로 채운다.
 *
 * 사용:
 *   php artisan booksys:fill-school "에이리스트"                    # 미리보기(dry-run)
 *   php artisan booksys:fill-school "에이리스트" --confirm          # 실제 반영 (기본 adult=성인)
 *   php artisan booksys:fill-school "에이리스트" --code=general --confirm
 *
 * 안전장치: 기본 dry-run, 대상 목록 표시, 실행 전 재확인, 트랜잭션 처리.
 */
class FillBookSchoolCode extends Command
{
    protected $signature = 'booksys:fill-school
                            {publisher : 출판사명 (부분 일치)}
                            {--code=adult : 채울 학교 코드 (elementary/middle/high/general/adult)}
                            {--confirm : 실제 반영 (미지정 시 dry-run)}';

    protected $description = '특정 출판사 도서 중 학교값이 빈 건을 지정 코드로 일괄 채움 (기본 dry-run)';

    public function handle(): int
    {
        $publisherName = trim((string) $this->argument('publisher'));
        $code   = (string) $this->option('code');
        $dryRun = ! $this->option('confirm');

        // 코드 유효성 (오타로 잘못된 값이 들어가는 것 방지)
        $codeRow = DB::table('codes')->where('group_code', 'school')->where('code', $code)->first();
        if (! $codeRow) {
            $valid = DB::table('codes')->where('group_code', 'school')->pluck('code')->implode(', ');
            $this->error("유효하지 않은 학교 코드: {$code}  (가능: {$valid})");
            return self::FAILURE;
        }

        $publishers = DB::table('publishers')->where('name', 'like', "%{$publisherName}%")->get(['id', 'name']);
        if ($publishers->isEmpty()) {
            $this->error("출판사를 찾을 수 없습니다: {$publisherName}");
            return self::FAILURE;
        }
        if ($publishers->count() > 1) {
            $this->warn('여러 출판사가 매칭됩니다:');
            foreach ($publishers as $p) $this->line("  #{$p->id} {$p->name}");
            $this->warn('모두 대상에 포함됩니다. 원하지 않으면 더 정확한 이름을 지정하세요.');
        }
        $pubIds = $publishers->pluck('id')->all();

        // 대상: 학교값이 NULL 이거나 빈 문자열
        $targets = DB::table('books')
            ->whereNull('deleted_at')
            ->whereIn('publisher_id', $pubIds)
            ->where(function ($w) { $w->whereNull('school_code')->orWhere('school_code', ''); })
            ->get(['id', 'isbn', 'title', 'school_code']);

        $this->info('=== 대상 확인 ===');
        $this->line('출판사   : ' . $publishers->pluck('name')->implode(', '));
        $this->line('채울 값  : ' . $code . ' (' . $codeRow->name . ')');
        $this->line('대상 건수: ' . $targets->count() . '건 (학교값이 비어있는 도서)');
        $this->newLine();

        if ($targets->isEmpty()) {
            $this->info('채울 대상이 없습니다. (이미 모두 값이 있거나 해당 출판사 도서 없음)');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'ISBN', '제목'],
            $targets->take(30)->map(fn ($b) => [$b->id, $b->isbn, mb_strimwidth($b->title ?? '', 0, 46, '…')])->all()
        );
        if ($targets->count() > 30) {
            $this->line('  ... 외 ' . ($targets->count() - 30) . '건');
        }
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY-RUN 입니다. 실제 반영하려면 --confirm 을 붙이세요:');
            $this->line("  php artisan booksys:fill-school \"{$publisherName}\" --code={$code} --confirm");
            return self::SUCCESS;
        }

        if (! $this->confirm($targets->count() . '건의 학교값을 [' . $codeRow->name . ']로 채웁니다. 진행할까요?', false)) {
            $this->warn('취소되었습니다.');
            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($targets, $code, &$updated) {
            $updated = DB::table('books')
                ->whereIn('id', $targets->pluck('id'))
                ->update(['school_code' => $code, 'updated_at' => now()]);
        });

        $this->newLine();
        $this->info("✓ {$updated}건의 학교값을 [{$codeRow->name}]로 채웠습니다.");
        return self::SUCCESS;
    }
}
