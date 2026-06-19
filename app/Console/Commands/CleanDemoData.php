<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 데모 데이터 정리 — 운영 진입 전 시드 데모 데이터 안전 삭제
 *
 * 사용:
 *   php artisan booksys:clean-demo            # dry-run (삭제될 항목만 표시)
 *   php artisan booksys:clean-demo --confirm  # 실제 삭제 실행
 */
class CleanDemoData extends Command
{
    protected $signature = 'booksys:clean-demo
                            {--confirm : 실제 삭제 실행 (이 옵션 없으면 dry-run)}';

    protected $description = '시드된 데모 사용자/주문/도서를 정리합니다 (운영 진입 전 사용)';

    public function handle(): int
    {
        $dryRun = ! $this->option('confirm');

        $this->info('=== BookSys 데모 데이터 정리 ===');
        $this->newLine();

        if ($dryRun) {
            $this->warn('▸ DRY-RUN 모드 — 실제로 삭제되지 않습니다. 삭제하려면 --confirm 옵션 추가');
            $this->newLine();
        }

        // 1. 데모 사용자 식별 (login_id 패턴) — 관리자(admin)는 절대 삭제하지 않음
        $demoUserQuery = DB::table('users')
            ->where(function ($q) {
                $q->where('login_id', 'like', 'dist%')
                  ->orWhere('login_id', 'like', 'agent%')
                  ->orWhere('login_id', 'like', 'academy%');
            })
            ->where('role_code', '!=', 'admin')   // ★ 관리자 계정(sysadmin00 등)은 항상 보호
            ->whereNull('deleted_at');

        $this->warn('주의: login_id가 dist*/agent*/academy* 패턴인 계정이 대상입니다. 운영 실계정이 같은 패턴이면 함께 삭제될 수 있으니 아래 목록을 반드시 확인하세요.');

        $demoUsers = $demoUserQuery->get(['id', 'login_id', 'name', 'role_code']);
        $demoUserIds = $demoUsers->pluck('id')->toArray();

        $this->info("▸ 데모 사용자: {$demoUsers->count()}명");
        if ($demoUsers->count() > 0) {
            $this->table(['ID', 'login_id', '이름', '역할'],
                $demoUsers->map(fn($u) => [$u->id, $u->login_id, $u->name, $u->role_code])->toArray()
            );
        }

        // 2. 데모 주문 (데모 사용자가 영업자/총판/학원으로 관련된 모든 주문)
        $demoOrders = DB::table('orders')->whereNull('deleted_at')
            ->where(function ($q) use ($demoUserIds) {
                $q->whereIn('agent_user_id', $demoUserIds)
                  ->orWhereIn('distributor_user_id', $demoUserIds)
                  ->orWhereIn('vendor_id', function ($sub) use ($demoUserIds) {
                      $sub->select('vendor_id')->from('vendor_users')->whereIn('user_id', $demoUserIds);
                  });
            });
        $demoOrderCount = $demoOrders->count();
        $this->info("▸ 데모 주문: {$demoOrderCount}건");

        // 3. 데모 학원 (vendor_users로 데모 사용자와 연결된)
        $demoVendorIds = DB::table('vendor_users')->whereIn('user_id', $demoUserIds)->pluck('vendor_id')->toArray();
        $demoVendorCount = DB::table('vendors')->whereIn('id', $demoVendorIds)->whereNull('deleted_at')->count();
        $this->info("▸ 데모 학원: {$demoVendorCount}곳");

        // 4. 데모 정산 레코드
        $demoSettlementCount = DB::table('settlement_records')
            ->whereIn('agent_user_id', $demoUserIds)
            ->orWhereIn('distributor_user_id', $demoUserIds)
            ->orWhereIn('vendor_id', $demoVendorIds)
            ->count();
        $this->info("▸ 데모 정산 레코드: {$demoSettlementCount}건");

        // 5. 데모 결제요청
        $demoPaymentRequestCount = DB::table('payment_requests')
            ->whereIn('vendor_id', $demoVendorIds)
            ->count();
        $this->info("▸ 데모 결제요청: {$demoPaymentRequestCount}건");

        $this->newLine();

        if ($demoUsers->isEmpty() && $demoOrderCount === 0 && $demoVendorCount === 0) {
            $this->info('정리할 데모 데이터가 없습니다.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('실제 삭제를 진행하려면 다음 명령을 실행하세요:');
            $this->line('  php artisan booksys:clean-demo --confirm');
            return self::SUCCESS;
        }

        // 최종 확인
        if (! $this->confirm("위 데모 데이터를 정말 삭제하시겠습니까? (되돌릴 수 없음)", false)) {
            $this->warn('취소되었습니다.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($demoUserIds, $demoVendorIds) {
            $now = now();

            // 정산 → 결제요청 → 주문 → 학원 → 사용자 순으로 정리
            DB::table('settlement_records')
                ->whereIn('agent_user_id', $demoUserIds)
                ->orWhereIn('distributor_user_id', $demoUserIds)
                ->orWhereIn('vendor_id', $demoVendorIds)
                ->delete();

            DB::table('payment_requests')->whereIn('vendor_id', $demoVendorIds)->delete();

            // order_items → orders (cascade 없음 가정, 명시적 삭제)
            $demoOrderIds = DB::table('orders')->where(function ($q) use ($demoUserIds, $demoVendorIds) {
                $q->whereIn('agent_user_id', $demoUserIds)
                  ->orWhereIn('distributor_user_id', $demoUserIds)
                  ->orWhereIn('vendor_id', $demoVendorIds);
            })->pluck('id')->toArray();
            DB::table('order_items')->whereIn('order_id', $demoOrderIds)->delete();
            DB::table('order_shipments')->whereIn('order_id', $demoOrderIds)->delete();
            DB::table('orders')->whereIn('id', $demoOrderIds)->update(['deleted_at' => $now]);

            // 학생: soft delete / 학급: hard delete (deleted_at 컬럼 없음)
            DB::table('students')->whereIn('class_id', function ($q) use ($demoVendorIds) {
                $q->select('id')->from('academy_classes')->whereIn('vendor_id', $demoVendorIds);
            })->update(['deleted_at' => $now]);
            DB::table('academy_classes')->whereIn('vendor_id', $demoVendorIds)->delete();

            // 학원-사용자 연결, 영업자-학원 할인 정리
            DB::table('vendor_users')->whereIn('vendor_id', $demoVendorIds)->delete();
            DB::table('agent_vendor_discounts')
                ->whereIn('agent_user_id', $demoUserIds)
                ->orWhereIn('vendor_id', $demoVendorIds)
                ->delete();
            DB::table('agent_vendor_book_discounts')
                ->whereIn('agent_user_id', $demoUserIds)
                ->orWhereIn('vendor_id', $demoVendorIds)
                ->delete();

            // 사용자 관계 (총판-영업자)
            DB::table('user_relations')
                ->whereIn('parent_user_id', $demoUserIds)
                ->orWhereIn('child_user_id', $demoUserIds)
                ->delete();

            // 재고 (총판이 데모면)
            DB::table('book_stocks')->whereIn('distributor_user_id', $demoUserIds)->delete();

            // 학원 (soft delete)
            DB::table('vendors')->whereIn('id', $demoVendorIds)->update(['deleted_at' => $now]);

            // 사용자 (soft delete)
            DB::table('users')->whereIn('id', $demoUserIds)->update(['deleted_at' => $now]);
        });

        $this->newLine();
        $this->info('✓ 데모 데이터 정리 완료');
        $this->warn('주의: 관리자 비밀번호를 아직 변경하지 않았다면 즉시 변경하세요.');

        return self::SUCCESS;
    }
}
