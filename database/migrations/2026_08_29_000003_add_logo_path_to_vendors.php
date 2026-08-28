<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 학원 로고.
 *
 * 학원 계정으로 로그인하면 좌측 상단이 BookSys 로고인데, 학원 입장에선 자기 학원
 * 화면처럼 보이는 게 자연스럽다. 로고가 없으면 학원명을 대신 보여준다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
