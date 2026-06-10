<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * books 테이블에 cover_file_name 컬럼 추가
 *
 * 용도: 출판사가 제공한 표지 이미지 파일명 (예: 'bricks_phonics_1.jpg')
 * → 별도 ZIP 일괄 업로드 시 이 파일명으로 매칭하여 저장
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('cover_file_name', 255)->nullable()->after('cover_path')
                  ->comment('표지 이미지 원본 파일명 (ZIP 업로드 매칭용)');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('cover_file_name');
        });
    }
};
