<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 대표 이용학원을 영업자·총판도 등록할 수 있게 되면서 소유자를 남긴다.
 * 영업자는 자기가 올린 것만 손댈 수 있고, 총판은 산하 영업자 것까지 본다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('featured_academies', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('is_active')
                  ->comment('등록자 — 영업자/총판/관리자');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('featured_academies', function (Blueprint $table) {
            $table->dropIndex(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};
