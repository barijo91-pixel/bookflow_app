<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 카카오(카카오페이) 심사용 계정 생성 — 이 계정으로 로그인하면 사이드바에 "교재 구매(심사용)" 메뉴가 노출된다.
 * (academy11 등 일반 학원 계정에는 계속 숨겨진 상태로 유지 — sidebar.blade.php 참고)
 *
 * 심사원이 화면을 눌러봐도 막히지 않도록, 소매 학원 주문에 필요한 것을 함께 만든다:
 *   사용자(academy) → 거래처(학원, 소매) → 담당 영업자 매핑 → 학급 → 학생
 * (담당 영업자가 없으면 /mypage/order/new 가 "매핑된 영업자가 없습니다" 경고만 표시됨)
 *
 * 사용:
 *   php artisan booksys:make-store-reviewer --password=비밀번호
 *   php artisan booksys:make-store-reviewer katest --password=비밀번호 --agent=agent01
 *
 * ※ 비밀번호는 저장소에 남기지 않도록 반드시 실행 시 옵션으로 전달할 것.
 * ※ 심사 종료 후에는 계정을 삭제하거나 비밀번호를 변경할 것.
 */
class MakeStoreReviewer extends Command
{
    /** 사이드바에서 심사용 메뉴가 노출되는 계정 (sidebar.blade.php 조건과 반드시 일치) */
    public const REVIEWER_LOGIN_ID = 'katest';

    protected $signature = 'booksys:make-store-reviewer
                            {login_id=katest : 심사용 계정 아이디}
                            {--password= : 비밀번호 (필수)}
                            {--name=심사담당 : 결제창에 들어갈 구매자명}
                            {--phone=01000000000 : 결제창에 들어갈 연락처 (PC 결제 시 필수값)}
                            {--vendor=심사용 학원 : 연결할 거래처(학원) 이름}
                            {--agent= : 매핑할 담당 영업자 login_id (미지정 시 첫 번째 활성 영업자)}
                            {--rate=0 : 영업자 할인율(%). 심사 시 정가 그대로 보이도록 기본 0}';

    protected $description = '카카오/PG 심사용 학원 계정 생성 (심사용 교재구매 메뉴 노출 + 도서주문 가능 상태)';

    public function handle(): int
    {
        $loginId    = trim((string) $this->argument('login_id'));
        $password   = (string) $this->option('password');
        $name       = trim((string) $this->option('name'));
        $phone      = preg_replace('/[^0-9]/', '', (string) $this->option('phone'));
        $vendorNm   = trim((string) $this->option('vendor'));
        $agentLogin = trim((string) $this->option('agent'));
        $rate       = (float) $this->option('rate');

        if ($password === '') {
            $this->error('--password=비밀번호 를 지정하세요. (예: --password=xxxx)');
            return self::FAILURE;
        }
        if ($loginId === '') {
            $this->error('login_id 가 비어있습니다.');
            return self::FAILURE;
        }

        // 사이드바 노출 조건과 다른 아이디면 메뉴가 안 뜬다 — 미리 경고
        if ($loginId !== self::REVIEWER_LOGIN_ID) {
            $this->warn('주의: 심사용 메뉴는 "' . self::REVIEWER_LOGIN_ID . '" 계정에만 노출됩니다.');
            $this->warn("입력한 '{$loginId}' 계정은 /mypage/store 로 URL 직접 접근만 가능합니다.");
        }

        $userId = $vendorId = $agentId = $classId = null;
        $agentName = null;

        try {
            DB::transaction(function () use (
                $loginId, $password, $name, $phone, $vendorNm, $agentLogin, $rate,
                &$userId, &$vendorId, &$agentId, &$classId, &$agentName
            ) {
                $now = now();

                // 1) 사용자 (있으면 갱신 / soft delete 상태면 복구)
                $existing = DB::table('users')->where('login_id', $loginId)->first();

                $payload = [
                    'name'                     => $name,
                    'phone'                    => $phone,
                    'password'                 => Hash::make($password),
                    'password_change_required' => 0,   // 심사원이 비번변경 화면에 갇히지 않도록
                    'role_code'                => 'academy',
                    'status_code'              => 'active',
                    'business_type'            => 'none',
                    'phone_verified_at'        => $now,
                    'approved_at'              => $now,
                    'deleted_at'               => null,
                    'updated_at'               => $now,
                ];

                if ($existing) {
                    DB::table('users')->where('id', $existing->id)->update($payload);
                    $userId = $existing->id;
                } else {
                    $userId = DB::table('users')->insertGetId(
                        $payload + ['login_id' => $loginId, 'created_at' => $now]
                    );
                }

                // 2) 거래처(학원) — 소매(retail)
                $vendor = DB::table('vendors')->where('name', $vendorNm)->whereNull('deleted_at')->first();
                if (! $vendor) {
                    $vendorId = DB::table('vendors')->insertGetId([
                        'name'         => $vendorNm,
                        'owner_name'   => $name,
                        'type_code'    => 'academy',
                        'trade_type'   => 'retail',
                        'status_code'  => 'active',
                        'mobile'       => $phone,
                        'payment_type' => 'cash',
                        'credit_limit' => 0,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                } else {
                    $vendorId = $vendor->id;
                }

                // 3) 학원-사용자 연결
                $linked = DB::table('vendor_users')->where('vendor_id', $vendorId)->where('user_id', $userId)->exists();
                if (! $linked) {
                    DB::table('vendor_users')->insert([
                        'vendor_id'  => $vendorId,
                        'user_id'    => $userId,
                        'role'       => 'owner',
                        'is_primary' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // 4) 담당 영업자 매핑 — 없으면 도서주문 페이지가 경고만 표시된다
                $agentQuery = DB::table('users')->where('role_code', 'agent')->whereNull('deleted_at');
                if ($agentLogin !== '') {
                    $agent = (clone $agentQuery)->where('login_id', $agentLogin)->first(['id', 'name']);
                    if (! $agent) {
                        throw new \RuntimeException("영업자 '{$agentLogin}' 를 찾을 수 없습니다. (--agent 확인)");
                    }
                } else {
                    $agent = (clone $agentQuery)->where('status_code', 'active')->orderBy('id')->first(['id', 'name']);
                    if (! $agent) {
                        throw new \RuntimeException('활성 영업자가 없습니다. --agent=아이디 로 지정하세요.');
                    }
                }
                $agentId   = $agent->id;
                $agentName = $agent->name;

                $mapped = DB::table('agent_vendor_discounts')
                    ->where('vendor_id', $vendorId)->where('agent_user_id', $agentId)->first();
                if ($mapped) {
                    DB::table('agent_vendor_discounts')->where('id', $mapped->id)->update([
                        'discount_rate' => $rate, 'is_active' => 1, 'ended_at' => null, 'updated_at' => $now,
                    ]);
                } else {
                    DB::table('agent_vendor_discounts')->insert([
                        'agent_user_id' => $agentId,
                        'vendor_id'     => $vendorId,
                        'discount_rate' => $rate,
                        'started_at'    => $now->toDateString(),
                        'is_active'     => 1,
                        'memo'          => '심사용',
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }

                // 5) 학급 (소매 주문은 학급 선택이 필요)
                $classId = DB::table('academy_classes')->where('vendor_id', $vendorId)->value('id');
                if (! $classId) {
                    $classId = DB::table('academy_classes')->insertGetId([
                        'vendor_id'  => $vendorId,
                        'name'       => '심사용 학급',
                        'grade_code' => 'elem_1',
                        'status'     => 'active',
                        'started_at' => $now->toDateString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // 6) 학생 (소매 주문은 학생 선택이 필요)
                $hasStudent = DB::table('students')->where('class_id', $classId)->whereNull('deleted_at')->exists();
                if (! $hasStudent) {
                    foreach (['학생1', '학생2'] as $sname) {
                        DB::table('students')->insert([
                            'vendor_id'  => $vendorId,
                            'class_id'   => $classId,
                            'name'       => $sname,
                            'grade_code' => 'elem_1',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                // 7) 학부모 연결 — PaymentRequestController::store() 는 학부모 연락처가 없으면
                //    결제요청을 생성하지 않고 건너뛴다(실패 처리). 심사원이 결제창까지 갈 수 있도록 연결.
                //    (이미 만들어진 학생 중 학부모가 없거나 연락처가 빈 경우도 함께 보정)
                $students = DB::table('students as s')
                    ->leftJoin('parents as p', 'p.id', '=', 's.parent_id')
                    ->where('s.class_id', $classId)
                    ->whereNull('s.deleted_at')
                    ->select('s.id', 's.name', 's.parent_id', 'p.phone as parent_phone')
                    ->get();

                foreach ($students as $s) {
                    if ($s->parent_id && ! empty($s->parent_phone)) {
                        continue;   // 이미 정상 연결됨
                    }
                    if ($s->parent_id) {
                        // 학부모는 있는데 연락처가 비어있음 → 연락처만 보정
                        DB::table('parents')->where('id', $s->parent_id)
                            ->update(['phone' => $phone, 'updated_at' => $now]);
                        continue;
                    }
                    $parentId = DB::table('parents')->insertGetId([
                        'name'       => $s->name . ' 학부모',
                        'phone'      => $phone,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('students')->where('id', $s->id)
                        ->update(['parent_id' => $parentId, 'updated_at' => $now]);
                }
            });
        } catch (\Throwable $e) {
            $this->error('실패: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✓ 심사용 계정 준비 완료');
        $this->line("  · 아이디     : {$loginId}   (user id: {$userId})");
        $this->line("  · 구매자명   : {$name}  /  연락처: {$phone}");
        $this->line("  · 거래처     : {$vendorNm}  (소매)");
        $this->line("  · 담당 영업자: {$agentName}  (할인율 {$rate}%)");
        $this->line("  · 학급/학생  : 심사용 학급 + 학생 2명 (학부모 연락처 {$phone} 연결)");
        $this->newLine();
        $this->line('  확인: 로그인 → 좌측 "교재 구매(심사용)" (결제창) / "도서주문" (주문 플로우)');
        $this->warn('  심사 종료 후에는 이 계정을 삭제하거나 비밀번호를 변경하세요.');

        return self::SUCCESS;
    }
}
