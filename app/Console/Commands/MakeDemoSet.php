<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 데모용 계정 한 세트(총판 → 영업자 → 학원)를 만든다.
 *
 * 셋이 서로 연결돼 있어야 주문이 끝까지 흐르므로 한 번에 만든다.
 * 아이디는 접두어로 묶어 한 세트임을 알 수 있게 한다 — 기본 demo 이면
 *   demo_dist / demo_agent / demo_academy
 *
 * 이미 있으면 건드리지 않고 연결만 맞춘다(여러 번 돌려도 안전).
 */
class MakeDemoSet extends Command
{
    protected $signature = 'booksys:make-demo-set
        {--prefix=demo : 아이디 접두어 (demo → demo_dist / demo_agent / demo_academy)}
        {--password= : 세 계정 공통 비밀번호 (생략하면 무작위 생성, 화면에 출력 안 함)}
        {--vendor-name= : 학원명 (기본: "데모학원")}
        {--rate=10 : 영업자→학원 할인율 %}
        {--stocks : 총판 취급 교재를 전체 교재로 채운다 (주문 테스트용)}';

    protected $description = '데모용 총판·영업자·학원 계정을 한 세트로 생성 (서로 연결까지)';

    public function handle(): int
    {
        $prefix = preg_replace('/[^a-z0-9]/', '', strtolower((string) $this->option('prefix')));
        if ($prefix === '') {
            $this->error('prefix 는 영문·숫자만 가능합니다.');
            return self::FAILURE;
        }
        // 비밀번호 — 주지 않으면 랜덤으로 만든다. 어느 경우에도 화면에 출력하지 않는다.
        // (출력하면 운영 로그·대화 기록에 남는다. 확인은 관리자 > 사용자 목록 > 비밀번호 초기화)
        $password = (string) $this->option('password');
        $generated = false;
        if ($password === '') {
            $password = $this->randomPassword(12);
            $generated = true;
        } elseif (strlen($password) < 8 || ! preg_match('/[a-zA-Z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            $this->error('--password 는 영문+숫자 8자 이상이어야 합니다.');
            return self::FAILURE;
        }

        $ids = [
            'distributor' => $prefix . '_dist',
            'agent'       => $prefix . '_agent',
            'academy'     => $prefix . '_academy',
        ];
        $vendorName = (string) ($this->option('vendor-name') ?: '데모학원');
        $rate       = (float) $this->option('rate');

        $this->line('만들 계정: ' . implode(' / ', $ids));

        DB::transaction(function () use ($ids, $password, $vendorName, $rate) {
            // ── 1) 총판
            $dist = $this->upsertUser($ids['distributor'], '데모총판', 'distributor', $password);
            // ── 2) 영업자
            $agent = $this->upsertUser($ids['agent'], '데모영업자', 'agent', $password);

            // 총판 ↔ 영업자 (영업자는 총판 1곳에만 소속 — 기존 관계가 있으면 그대로 둔다)
            $hasRel = DB::table('user_relations')
                ->where('child_user_id', $agent->id)
                ->where('relation_type', 'distributor_agent')
                ->where('status', 'active')->exists();
            if (! $hasRel) {
                DB::table('user_relations')->insert([
                    'parent_user_id' => $dist->id,
                    'child_user_id'  => $agent->id,
                    'relation_type'  => 'distributor_agent',
                    'status'         => 'active',
                    'started_at'     => now()->toDateString(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $this->line('  총판–영업자 연결 생성');
            }

            // ── 3) 학원(거래처) + 학원 계정
            $vendorId = DB::table('vendors')->where('name', $vendorName)->whereNull('deleted_at')->value('id');
            if (! $vendorId) {
                $vendorId = DB::table('vendors')->insertGetId([
                    'name'                 => $vendorName,
                    'owner_name'           => '데모원장',
                    'type_code'            => 'academy',
                    'trade_type'           => 'retail',
                    'default_ship_to_type' => 'parent',
                    'status_code'          => 'active',
                    'payment_type'         => 'cash',
                    'credit_limit'         => 0,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $this->line('  학원 생성');
            }

            $academy = $this->upsertUser($ids['academy'], '데모원장', 'academy', $password);
            if (! DB::table('vendor_users')->where('vendor_id', $vendorId)->where('user_id', $academy->id)->exists()) {
                DB::table('vendor_users')->insert([
                    'vendor_id' => $vendorId, 'user_id' => $academy->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // ── 4) 영업자 ↔ 학원 (할인율 매핑이 있어야 주문 화면이 열린다)
            if (! DB::table('agent_vendor_discounts')
                    ->where('agent_user_id', $agent->id)->where('vendor_id', $vendorId)->exists()) {
                DB::table('agent_vendor_discounts')->insert([
                    'agent_user_id' => $agent->id,
                    'vendor_id'     => $vendorId,
                    'discount_rate' => $rate,
                    'started_at'    => now()->toDateString(),
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $this->line('  영업자–학원 매핑 생성 (할인율 ' . $rate . '%)');
            }

            // ── 5) 학급 하나 — 소매 주문은 학급이 있어야 끝까지 간다
            if (! DB::table('academy_classes')->where('vendor_id', $vendorId)->exists()) {
                DB::table('academy_classes')->insert([
                    'vendor_id' => $vendorId, 'name' => '데모반',
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->line('  학급 "데모반" 생성');
            }

            $this->newLine();
            $this->info('완료 — 총판 #' . $dist->id . ' / 영업자 #' . $agent->id
                      . ' / 학원 #' . $vendorId . '(계정 #' . $academy->id . ')');
        });

        // ── 6) 총판 취급 교재 (선택) — 없으면 주문 화면에 교재가 안 보인다
        if ($this->option('stocks')) {
            $dist = User::where('login_id', $ids['distributor'])->first();
            $this->call('booksys:copy-stocks', ['scope' => 'all', 'distributor' => $dist->login_id, '--apply' => true]);
        } else {
            $this->warn('총판 취급 교재는 안 넣었습니다. 주문 테스트까지 하려면 --stocks 를 붙이거나');
            $this->warn('총판 계정으로 재고 업로드를 해주세요. (교재가 없으면 주문 화면이 비어 보입니다)');
        }

        $this->newLine();
        $this->line('아이디: ' . implode(' / ', $ids));
        if ($generated) {
            $this->warn('비밀번호는 무작위로 만들었고 화면에 출력하지 않습니다.');
            $this->warn('관리자 > 사용자 목록에서 각 계정의 [비밀번호 초기화] 를 눌러 임시 비번을 확인하세요.');
        } else {
            $this->line('비밀번호는 지정하신 값입니다. (보안상 출력하지 않습니다)');
        }
        return self::SUCCESS;
    }

    /** 있으면 그대로 두고, 없으면 만든다 */
    private function upsertUser(string $loginId, string $name, string $role, string $password): User
    {
        $user = User::where('login_id', $loginId)->first();
        if ($user) {
            $this->line("  {$loginId} — 이미 있음 (건드리지 않음)");
            return $user;
        }
        $user = User::create([
            'login_id'                 => $loginId,
            'name'                     => $name,
            'phone'                    => '01000000000',
            'password'                 => $password,   // hashed cast
            'password_change_required' => false,       // 데모라 첫 로그인 강제변경 없음
            'role_code'                => $role,
            'status_code'              => 'active',
            'business_type'            => 'none',
            'approved_at'              => now(),
        ]);
        $this->line("  {$loginId} 생성 ({$role})");
        return $user;
    }
    /** 영문+숫자 혼합 비밀번호 */
    private function randomPassword(int $len): string
    {
        $pool = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $pw = '';
            for ($i = 0; $i < $len; $i++) $pw .= $pool[random_int(0, strlen($pool) - 1)];
        } while (! preg_match('/[a-zA-Z]/', $pw) || ! preg_match('/[0-9]/', $pw));
        return $pw;
    }

}
