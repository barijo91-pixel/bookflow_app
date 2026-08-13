<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 영업자별 학원 가입 초대 링크 토큰.
 * 영업자가 이 토큰이 든 URL 을 학원에 보내면, 학원이 스스로 가입하면서
 * 그 영업자 담당으로 자동 연결된다. 유출 시 재발급(토큰 교체)하면 옛 링크는 죽는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invite_token', 40)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['invite_token']);
            $table->dropColumn('invite_token');
        });
    }
};
