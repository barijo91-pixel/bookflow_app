<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 로그인 ID 시스템: 이메일 → 일반 아이디
 *
 * - login_id 컬럼 추가 (6~50자 영문+숫자, 신규 가입 시)
 * - email은 옵션 컬럼으로 전환 (알림 수신용, nullable, unique 해제)
 * - 기존 사용자: email 앞부분에서 영숫자만 추출 → 6자 미만이면 "01" 패딩 → 중복 시 숫자 증가
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) login_id 컬럼 (nullable로 임시 추가)
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_id', 50)->nullable()->after('id');
        });

        // 2) 기존 사용자 login_id 자동 채우기
        DB::table('users')->orderBy('id')->cursor()->each(function ($user) {
            $emailPrefix = $user->email ? explode('@', $user->email)[0] : 'user'.$user->id;
            $prefix = preg_replace('/[^a-zA-Z0-9]/', '', $emailPrefix);
            if ($prefix === '') {
                $prefix = 'user'.$user->id;
            }
            if (strlen($prefix) < 6) {
                $prefix .= '01';
            }
            // 중복 회피
            $base = $prefix;
            $i = 0;
            while (DB::table('users')->where('login_id', $prefix)->where('id', '!=', $user->id)->exists()) {
                $i++;
                $prefix = $base.sprintf('%02d', $i);
            }
            DB::table('users')->where('id', $user->id)->update(['login_id' => $prefix]);
        });

        // 3) login_id를 NOT NULL + UNIQUE 로 확정
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_id', 50)->nullable(false)->change();
            $table->unique('login_id', 'users_login_id_unique');
        });

        // 4) email은 옵션(알림 수신용)으로: unique 해제 + nullable
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_login_id_unique');
            $table->dropColumn('login_id');
        });
        // email은 unique 복원 + NOT NULL (롤백 시 데이터에 NULL 있으면 실패할 수 있음)
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->unique('email', 'users_email_unique');
        });
    }
};
