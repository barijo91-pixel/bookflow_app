<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * books.grade_code, books.semester_code 제거.
 *
 * 사유: 학년/학기는 M:N 관계 (한 책이 여러 학년 대상일 수 있음).
 * 기존 book_targets 테이블 (target_type='grade'/'level'/'school') 활용으로 통일.
 * semester는 target_type='semester' 로 추가.
 *
 * 직전 마이그레이션 2026_05_25_000001 의 반대 작업.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // 인덱스 먼저 drop
            $table->dropIndex(['school_code', 'grade_code']);
            $table->dropIndex(['semester_code']);
            $table->dropColumn(['grade_code', 'semester_code']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('grade_code', 20)->nullable()->after('school_code');
            $table->string('semester_code', 10)->nullable()->after('grade_code');
            $table->index(['school_code', 'grade_code']);
            $table->index('semester_code');
        });
    }
};
