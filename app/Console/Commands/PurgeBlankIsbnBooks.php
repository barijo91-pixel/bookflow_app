<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ISBN이 비어있는 도서 정리 (soft delete).
 * 사용:
 *   php artisan booksys:purge-blank-isbn            # 미리보기(dry-run)
 *   php artisan booksys:purge-blank-isbn --confirm  # 실제 삭제
 */
class PurgeBlankIsbnBooks extends Command
{
    protected $signature = 'booksys:purge-blank-isbn {--confirm : 실제 삭제(soft delete) 실행}';
    protected $description = 'ISBN이 비어있는 도서를 soft delete로 정리 (기본 dry-run)';

    public function handle(): int
    {
        $rows = DB::table('books')
            ->where(function ($w) { $w->whereNull('isbn')->orWhere('isbn', ''); })
            ->whereNull('deleted_at')
            ->get(['id', 'title', 'isbn']);

        if ($rows->isEmpty()) {
            $this->info('ISBN 공백 도서가 없습니다.');
            return self::SUCCESS;
        }

        $this->warn("ISBN 공백 도서 {$rows->count()}건:");
        $linkedOrder = 0; $linkedClass = 0;
        foreach ($rows as $r) {
            $oi = DB::table('order_items')->where('book_id', $r->id)->count();
            $cb = DB::table('class_books')->where('book_id', $r->id)->count();
            if ($oi) $linkedOrder++;
            if ($cb) $linkedClass++;
            $flag = ($oi || $cb) ? "  [주문 {$oi}·학급 {$cb} 연결]" : '';
            $this->line("  #{$r->id}  " . mb_substr($r->title ?? '(제목없음)', 0, 40) . $flag);
        }
        $this->newLine();
        $this->line("연결 현황: 주문 이력 {$linkedOrder}건(스냅샷 보존), 학급 교재 {$linkedClass}건");

        if (! $this->option('confirm')) {
            $this->newLine();
            $this->warn('미리보기(dry-run)입니다. 실제 삭제하려면 --confirm 을 붙이세요.');
            return self::SUCCESS;
        }

        $count = DB::table('books')->whereIn('id', $rows->pluck('id'))
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        $this->info("ISBN 공백 도서 {$count}건을 삭제(soft delete)했습니다.");
        return self::SUCCESS;
    }
}
