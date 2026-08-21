<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 운영 오픈 전 거래 데이터 초기화
 *
 * 유지: 회원(users/vendors/연결), 도서(books), 총판 재고(book_stocks),
 *       기본정보(codes/site_settings/regions/publishers)
 * 삭제: 주문 전체, 결제요청, 정산, 학급/학생/학부모, 알림 발송이력, 감사로그
 *
 * 사용:
 *   php artisan booksys:reset-operations            # 미리보기(dry-run)
 *   php artisan booksys:reset-operations --confirm  # 실제 삭제
 */
class ResetOperationData extends Command
{
    protected $signature = 'booksys:reset-operations {--confirm : 실제 삭제 실행 (미지정 시 dry-run)}{--production : 운영 환경에서 실행할 때 반드시 함께 지정}';
    protected $description = '오픈 전 거래 데이터 초기화 (회원·도서·재고·기본정보는 유지)';

    /** 삭제 순서 — 자식 → 부모 (FK 제약 회피) */
    private const DELETE_ORDER = [
        // 주문 관련
        'order_students'     => '주문-학생 연결',
        'order_shipments'    => '주문 배송',
        'order_status_logs'  => '주문 상태이력',
        'order_items'        => '주문 상세',
        // 결제/정산 (orders 보다 먼저 — FK 없어도 논리적 자식)
        'settlement_records' => '정산 레코드',
        'payment_requests'   => '결제 요청',
        'orders'             => '주문',
        // 학급/학생/학부모
        'parent_share_links' => '학부모 공유링크',
        'class_books'        => '학급 교재',
        'students'           => '학생',
        'parents'            => '학부모',
        'academy_classes'    => '학급',
        // 로그
        'notifications'      => '알림 발송이력',
        'audit_logs'         => '감사 로그',
    ];

    /** 반드시 유지되어야 하는 테이블 (안전 확인용) */
    private const KEEP = [
        'users' => '회원', 'vendors' => '거래처(학원)', 'vendor_users' => '학원-회원 연결',
        'user_relations' => '총판-영업자 연결', 'agent_vendor_discounts' => '영업자-학원 할인',
        'books' => '도서', 'book_stocks' => '총판 재고', 'publishers' => '출판사',
        'codes' => '코드', 'site_settings' => '사이트 설정', 'regions' => '지역',
    ];

    public function handle(): int
    {
        // 운영(production) 환경에서는 --production 을 함께 요구한다.
        // 과거 운영 데이터를 대량 삭제해 기록이 사라진 사고가 있었다.
        if (app()->environment('production') && ! $this->option('production')) {
            $this->error('운영(production) 환경입니다. 정말 실행하려면 --production 을 함께 붙이세요.');
            return self::FAILURE;
        }

        $dryRun = ! $this->option('confirm');

        $this->info('=== BookSys 운영 데이터 초기화 ===');
        $this->newLine();
        if ($dryRun) {
            $this->warn('▸ DRY-RUN — 실제로 삭제되지 않습니다. 삭제하려면 --confirm 추가');
            $this->newLine();
        }

        // 삭제 대상 집계
        $this->warn('▸ 삭제 대상');
        $rows = [];
        $totalDelete = 0;
        foreach (self::DELETE_ORDER as $table => $label) {
            if (! Schema::hasTable($table)) continue;
            $cnt = DB::table($table)->count();
            $rows[] = [$label, $table, number_format($cnt)];
            $totalDelete += $cnt;
        }
        $this->table(['항목', '테이블', '건수'], $rows);

        // 유지 대상 확인 (사용자가 눈으로 검증)
        $this->info('▸ 유지 (건드리지 않음)');
        $keepRows = [];
        foreach (self::KEEP as $table => $label) {
            if (! Schema::hasTable($table)) continue;
            $keepRows[] = [$label, $table, number_format(DB::table($table)->count())];
        }
        $this->table(['항목', '테이블', '건수'], $keepRows);

        if ($totalDelete === 0) {
            $this->info('삭제할 데이터가 없습니다.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('실제 삭제하려면:');
            $this->line('  php artisan booksys:reset-operations --confirm');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('※ 되돌릴 수 없습니다. 삭제 전 DB 백업을 권장합니다.');
        if (! $this->confirm("위 {$totalDelete}건을 삭제할까요?", false)) {
            $this->warn('취소되었습니다.');
            return self::SUCCESS;
        }

        $deleted = [];
        DB::transaction(function () use (&$deleted) {
            foreach (self::DELETE_ORDER as $table => $label) {
                if (! Schema::hasTable($table)) continue;
                $n = DB::table($table)->delete();
                if ($n) $deleted[$label] = $n;
            }
        });

        $this->newLine();
        $this->info('✓ 초기화 완료');
        foreach ($deleted as $label => $n) {
            $this->line('  · ' . str_pad($label, 20) . number_format($n) . '건 삭제');
        }
        $this->newLine();
        $this->line('회원·도서·재고·기본정보는 그대로 유지되었습니다.');

        return self::SUCCESS;
    }
}
