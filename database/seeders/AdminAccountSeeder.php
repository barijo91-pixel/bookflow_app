<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('users')->where('email', 'admin@bookflow.local')->exists()) {
            $this->command->info('admin@bookflow.local 이미 존재 — 건너뜀.');
            return;
        }

        DB::table('users')->insert([
            'email'        => 'admin@bookflow.local',
            'password'     => Hash::make('1234'),
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

        $this->command->warn('[보안] admin@bookflow.local / 1234 — 운영 시 즉시 비밀번호 변경 필수');
    }
}
