<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('users')->where('login_id', 'sysadmin00')->exists()) {
            $this->command->info('sysadmin00 이미 존재 — 건너뜀.');
            return;
        }

        DB::table('users')->insert([
            'login_id'     => 'sysadmin00',
            'email'        => 'admin@bookflow.local',
            'password'     => Hash::make('admin1234'),
            'password_change_required' => true,
            'name'         => '시스템관리자',
            'phone'        => '01000000000',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'role_code'    => 'admin',
            'admin_level'  => 'super',
            'status_code'  => 'active',
            'approved_at'  => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->command->warn('[보안] 관리자 아이디: sysadmin00 / 임시 비번: admin1234 — 첫 로그인 시 비번 변경 강제됨');
    }
}
