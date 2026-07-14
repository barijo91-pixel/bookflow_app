<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 도서 카탈로그 초기화 — PG 심사용 도서 몇 건만 남기고 나머지 도서 + 모든 거래 데이터를 삭제.
 * 새 도서 엑셀을 올리기 전 깨끗하게 비우는 용도.
 *
 * 남길 도서 기준:
 *   - --keep-isbn 로 ISBN 지정 시 그 도서들만 유지
 *   - 미지정 시 /mypage/store 에 노출되는 "최신 판매도서 3건"을 유지 (PG 심사용과 동일)
 *
 * 삭제 대상:
 *   - 남길 도서를 제외한 모든 도서 (물리 삭제 — cascade 로 재고/파일/대상/영업자할인 함께 제거)
 *   - 모든 주문 관련 데이터 (orders → order_items·배송·상태로그·주문학생 cascade)
 *   - 모든 결제요청(payment_requests), 정산(settlement_records)
 *   - 삭제 도서에 연결된 학급교재(class_books)  ※ 학급/학생 자체는 유지
 *
 * 사용:
 *   php artisan booksys:reset-catalog                              # 미리보기(dry-run)
 *   php artisan booksys:reset-catalog --confirm                    # 실제 실행 (최신 3건 유지)
 *   php artisan booksys:reset-catalog --keep-isbn=978... --keep-isbn=978... --confirm
 */
class ResetCatalog extends Command
{
    protected $signature = 'booksys:reset-catalog
                            {--keep-isbn=* : 남길 도서 ISBN (여러 개 가능). 미지정 시 최신 판매도서 3건 유지}
                            {--confirm : 실제 삭제 실행 (미지정 시 dry-run)}';

    protected $description = 'PG 심사용 도서만 남기고 나머지 도서 + 모든 거래 데이터를 삭제 (새 엑셀 업로드 전 초기화)';

    public function handle(): int
    {
        $dryRun = ! $this->option('confirm');

        $this->info('=== BookSys 도서 카탈로그 초기화 ===');
        $this->newLine();
        if ($dryRun) {
            $this->warn('▸ DRY-RUN 모드 — 실제로 삭제되지 않습니다. 삭제하려면 --confirm 옵션 추가');
            $this->newLine();
        }

        // 1) 남길 도서(keep) 결정 ------------------------------------------------
        $keepIsbns = array_filter(array_map('trim', (array) $this->option('keep-isbn')));

        if (! empty($keepIsbns)) {
            $keep = DB::table('books')->whereNull('deleted_at')
                ->whereIn('isbn', $keepIsbns)
                ->get(['id', 'isbn', 'title', 'price']);
        } else {
            // /mypage/store 와 동일한 기준: 최신 판매도서 3건
            $keep = DB::table('books')->whereNull('deleted_at')
                ->where('status_code', 'selling')
                ->orderByDesc('id')
                ->limit(3)
                ->get(['id', 'isbn', 'title', 'price']);
        }

        $keepIds = $keep->pluck('id')->all();

        // ★ 안전장치: 남길 도서가 하나도 없으면 전체 삭제를 막는다
        if (empty($keepIds)) {
            $this->error('남길 도서가 0건입니다. (전체 삭제 방지) --keep-isbn 으로 남길 ISBN을 지정하세요.');
            return self::FAILURE;
        }

        $this->info('▸ 유지할 도서 ' . count($keepIds) . '건:');
        $this->table(
            ['ID', 'ISBN', '제목', '정가'],
            $keep->map(fn ($b) => [
                $b->id, $b->isbn, mb_strimwidth($b->title ?? '', 0, 44, '…'), number_format($b->price ?? 0),
            ])->all()
        );

        // 2) 삭제 대상 집계 ------------------------------------------------------
        $totalBooks   = DB::table('books')->count();                                   // soft-deleted 포함
        $deleteBooks  = DB::table('books')->whereNotIn('id', $keepIds)->count();
        $orders       = DB::table('orders')->count();
        $orderItems   = DB::table('order_items')->count();
        $payReqs      = DB::table('payment_requests')->count();
        $settlements  = DB::table('settlement_records')->count();
        $classBooks   = DB::table('class_books')->whereNotIn('book_id', $keepIds)->count();

        $this->newLine();
        $this->warn('▸ 삭제 대상:');
        $this->line("  · 도서                : {$deleteBooks}건  (전체 {$totalBooks}건 중 " . count($keepIds) . '건 유지)');
        $this->line("  · 주문(orders)        : {$orders}건  → 주문상세·배송·상태로그·주문학생 함께 삭제");
        $this->line("  · 주문상세(order_items): {$orderItems}건");
        $this->line("  · 결제요청            : {$payReqs}건");
        $this->line("  · 정산레코드          : {$settlements}건");
        $this->line("  · 학급교재(class_books): {$classBooks}건  ※ 학급/학생 자체는 유지");
        $this->line('  · (도서 삭제 시) 재고·도서파일·판매대상·영업자별할인 은 cascade 로 함께 삭제');
        $this->newLine();

        if ($dryRun) {
            $this->warn('실제 삭제를 진행하려면:');
            $this->line('  php artisan booksys:reset-catalog --confirm');
            return self::SUCCESS;
        }

        // 3) 실제 삭제 ----------------------------------------------------------
        if (! $this->confirm('위 내용대로 삭제를 진행할까요? (되돌릴 수 없음)', false)) {
            $this->warn('취소되었습니다.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($keepIds) {
            // (a) 주문에 FK가 없는 테이블 먼저 정리
            DB::table('payment_requests')->delete();
            DB::table('settlement_records')->delete();

            // (b) 모든 주문 물리 삭제 → order_items/배송/상태로그/주문학생 cascade
            DB::table('orders')->delete();

            // (c) 삭제 도서에 걸린 학급교재(restrict) 먼저 제거
            DB::table('class_books')->whereNotIn('book_id', $keepIds)->delete();

            // (d) 도서 물리 삭제 → 재고/파일/대상/영업자할인 cascade
            DB::table('books')->whereNotIn('id', $keepIds)->delete();
        });

        $remain = DB::table('books')->count();
        $this->newLine();
        $this->info("✓ 초기화 완료 — 남은 도서 {$remain}건");
        $this->line('이제 새 도서 엑셀을 업로드하세요. (남긴 심사용 ISBN과 중복되지 않도록 주의)');

        return self::SUCCESS;
    }
}
