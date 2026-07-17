<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 카카오(카카오페이) 심사용 계정 생성 — 이 계정으로 로그인하면 사이드바에 "교재 구매(심사용)" 메뉴가 노출된다.
 * (academy11 등 일반 학원 계정에는 계속 숨겨진 상태로 유지 — sidebar.blade.php 참고)
 *
 * 사용:
 *   php artisan booksys:make-store-reviewer --password=비밀번호
 *   php artisan booksys:make-store-reviewer katest --password=비밀번호
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
                            {--vendor=심사용 학원 : 연결할 거래처(학원) 이름}';

    protected $description = 'PG 심사용 학원 계정 생성/갱신 (로그인 시 심사용 교재구매 메뉴 노출)';

    public function handle(): int
    {
        $loginId  = trim((string) $this->argument('login_id'));
        $password = (string) $this->option('password');
        $name     = trim((string) $this->option('name'));
        $phone    = preg_replace('/[^0-9]/', '', (string) $this->option('phone'));
        $vendorNm = trim((string) $this->option('vendor'));

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

        $userId = null;

        DB::transaction(function () use ($loginId, $password, $name, $phone, $vendorNm, &$userId) {
            $now = now();

            // 1) 사용자 (있으면 갱신 / soft delete 상태면 복구)
            $existing = DB::table('users')->where('login_id', $loginId)->first();

            $payload = [
                'name'                     => $name,
                'phone'                    => $phone,
                'password'                 => Hash::make($password),
                'password_change_required' => 0,
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

            // 2) 거래처(학원) — 없으면 생성
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
        });

        $this->newLine();
        $this->info('✓ 심사용 계정 준비 완료');
        $this->line("  · 아이디   : {$loginId}   (user id: {$userId})");
        $this->line("  · 구매자명 : {$name}");
        $this->line("  · 연락처   : {$phone}");
        $this->line("  · 거래처   : {$vendorNm}");
        $this->newLine();
        $this->line('  로그인 후 좌측 메뉴 "교재 구매(심사용)" 또는 /mypage/store 로 접속하면 결제창 확인 가능합니다.');
        $this->warn('  심사 종료 후에는 이 계정을 삭제하거나 비밀번호를 변경하세요.');

        return self::SUCCESS;
    }
}
