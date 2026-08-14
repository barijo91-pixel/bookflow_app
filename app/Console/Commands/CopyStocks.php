<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 총판 재고(취급 교재)를 다른 총판으로 복사.
 *
 * 새 총판이 취급 교재를 등록하기 전에는 영업자 화면에 교재가 하나도 안 보인다.
 * 실데이터를 받기 전 테스트용으로 기존 총판의 취급 목록을 그대로 깔아줄 때 쓴다.
 *
 *   php artisan booksys:copy-stocks distA01 tough7128            # 미리보기
 *   php artisan booksys:copy-stocks distA01 tough7128 --apply    # 실행
 *   php artisan booksys:copy-stocks distA01 tough7128 --undo     # 복사분 제거(대상 총판 재고 전체 삭제)
 */
class CopyStocks extends Command
{
    protected $signature = 'booksys:copy-stocks
        {from : 원본 총판 login_id (또는 all = 판매중 교재 전체를 취급 등록)}
        {to : 대상 총판 login_id}
        {--qty= : 복사 시 수량을 이 값으로 고정 (미지정이면 원본 수량 그대로)}
        {--apply : 실제 반영 (기본은 미리보기)}
        {--undo : 대상 총판의 재고를 전부 삭제 (테스트 데이터 정리용)}';

    protected $description = '총판 취급 교재(재고)를 다른 총판으로 복사 — 신규 총판 테스트용';

    public function handle(): int
    {
        $fromAll = strtolower($this->argument('from')) === 'all';

        $to = $this->distributor($this->argument('to'));
        if (! $to) return self::FAILURE;

        $apply = (bool) $this->option('apply');

        if ($this->option('undo')) {
            return $this->undo($to, $apply);
        }

        if ($fromAll) {
            // 원본 총판이 하나도 없을 때 — 판매중 교재 전체를 취급으로 등록한다.
            // 수량은 미지정 시 0 (취급은 하지만 재고는 아직 없음)
            $from = (object) ['id' => null, 'login_id' => 'all', 'name' => '판매중 교재 전체'];
            $src = DB::table('books')->whereNull('deleted_at')->where('status_code', 'selling')
                ->orderBy('id')
                ->get(['id as book_id'])
                ->map(fn ($b) => (object) ['book_id' => $b->book_id, 'qty' => 0, 'low_stock_threshold' => 5]);
        } else {
            $from = $this->distributor($this->argument('from'));
            if (! $from) return self::FAILURE;

            if ($from->id === $to->id) {
                $this->error('원본과 대상이 같은 총판입니다.');
                return self::FAILURE;
            }

            $src = DB::table('book_stocks')->where('distributor_user_id', $from->id)
                ->get(['book_id', 'qty', 'low_stock_threshold']);
        }

        if ($src->isEmpty()) {
            $this->error($fromAll
                ? '판매중 상태인 교재가 없습니다.'
                : "원본 총판 {$from->login_id} 에 재고가 없습니다.");
            return self::FAILURE;
        }

        $existing = DB::table('book_stocks')->where('distributor_user_id', $to->id)
            ->pluck('book_id')->all();

        $new  = $src->reject(fn ($s) => in_array($s->book_id, $existing));
        $skip = $src->count() - $new->count();
        $qty  = $this->option('qty');

        $this->info("원본 {$from->name}" . ($fromAll ? '' : "({$from->login_id})") . " → 대상 {$to->name}({$to->login_id})");
        $this->line("  원본 취급 {$src->count()}종 / 새로 넣을 것 {$new->count()}종"
            . ($skip ? " / 이미 있어 건너뜀 {$skip}종" : ''));
        if ($qty !== null) $this->line("  수량을 전부 {$qty} 로 고정");

        if ($new->isEmpty()) {
            $this->line('  반영할 것이 없습니다.');
            return self::SUCCESS;
        }

        // 무엇이 들어가는지 눈으로 확인 (상위 10종)
        $titles = DB::table('books')->whereIn('id', $new->pluck('book_id'))->pluck('title', 'id');
        foreach ($new->take(10) as $s) {
            $this->line('    - ' . ($titles[$s->book_id] ?? "book#{$s->book_id}")
                . ' (' . ($qty ?? $s->qty) . '권)');
        }
        if ($new->count() > 10) $this->line('    ... 외 ' . ($new->count() - 10) . '종');

        if (! $apply) {
            $this->newLine();
            $this->warn('미리보기입니다. 실제로 넣으려면 --apply 를 붙이세요.');
            return self::SUCCESS;
        }

        $now  = now();
        $rows = $new->map(fn ($s) => [
            'book_id'             => $s->book_id,
            'distributor_user_id' => $to->id,
            'qty'                 => $qty !== null ? (int) $qty : $s->qty,
            'low_stock_threshold' => $s->low_stock_threshold,
            'reserved_qty'        => 0,
            'created_at'          => $now,
            'updated_at'          => $now,
        ])->all();

        DB::table('book_stocks')->insert($rows);

        $this->newLine();
        $this->info('완료 — ' . count($rows) . '종 복사됨.');
        $this->line("되돌리려면: php artisan booksys:copy-stocks {$from->login_id} {$to->login_id} --undo --apply");
        if ($fromAll) {
            $this->line('수량은 0입니다 — 실제 입고 수량은 관리자 > 재고관리에서 조정하세요.');
        }
        return self::SUCCESS;
    }

    private function undo($to, bool $apply): int
    {
        $cnt = DB::table('book_stocks')->where('distributor_user_id', $to->id)->count();
        $this->info("대상 {$to->name}({$to->login_id}) 재고 {$cnt}종");

        if ($cnt === 0) {
            $this->line('  지울 것이 없습니다.');
            return self::SUCCESS;
        }
        if (! $apply) {
            $this->warn('미리보기입니다. 실제로 지우려면 --apply 를 붙이세요.');
            return self::SUCCESS;
        }

        DB::table('book_stocks')->where('distributor_user_id', $to->id)->delete();
        $this->info("삭제 완료 — {$cnt}종");
        return self::SUCCESS;
    }

    private function distributor(string $loginId)
    {
        $u = DB::table('users')->where('login_id', $loginId)->whereNull('deleted_at')
            ->first(['id', 'login_id', 'name', 'role_code']);
        if (! $u) {
            $this->error("총판을 찾을 수 없습니다: {$loginId}");
            return null;
        }
        if ($u->role_code !== 'distributor') {
            $this->error("{$loginId} 는 총판이 아닙니다 (role={$u->role_code})");
            return null;
        }
        return $u;
    }
}
