<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * 약한 기본 비밀번호를 가진 사용자에게 password_change_required = true 플래그 설정.
 *
 * 운영 배포 시 1회 실행:
 *   php artisan security:flag-weak-passwords
 *
 * 점검 대상 비밀번호: 1234, password, 0000, admin, qwerty 등
 * 일치하는 사용자는 다음 로그인 시 비밀번호 변경이 강제됨.
 */
class FlagWeakPasswords extends Command
{
    protected $signature = 'security:flag-weak-passwords {--dry-run : 실제 변경 없이 대상만 출력}';
    protected $description = '약한 기본 비밀번호 사용자에게 비번 변경 강제 플래그 설정';

    /** 점검할 약한 비밀번호 목록 */
    const WEAK_PASSWORDS = [
        '1234', '12345', '123456', '1234567', '12345678',
        'password', 'password1', 'passw0rd',
        'admin', 'admin123', 'admin1234',
        '0000', '00000000',
        'qwerty', 'qwerty123',
        'bookflow', 'booksys',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? '[DRY RUN] 변경 없이 대상만 출력합니다.' : '약한 비밀번호 사용자를 검사합니다.');

        // 이미 flag된 사용자는 건너뜀
        $users = User::where('password_change_required', false)
            ->orWhereNull('password_change_required')
            ->get();

        $hit = 0;
        foreach ($users as $user) {
            foreach (self::WEAK_PASSWORDS as $weak) {
                if (Hash::check($weak, $user->password)) {
                    $hit++;
                    $this->warn(sprintf(
                        '  [%d] %s (%s) — 약한 비번 "%s" 사용 중',
                        $user->id, $user->email, $user->role_code, $weak
                    ));
                    if (! $dryRun) {
                        $user->password_change_required = true;
                        $user->save();
                    }
                    break;
                }
            }
        }

        if ($hit === 0) {
            $this->info('약한 비밀번호 사용자가 없습니다.');
        } else {
            $action = $dryRun ? '발견 (실제 변경 없음)' : '플래그 설정 완료';
            $this->info("총 {$hit}명 {$action}.");
        }

        return self::SUCCESS;
    }
}
