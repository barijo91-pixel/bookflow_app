<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * books 테이블에 출판사 자체 도서코드(publisher_code) 추가
 *
 * 용도:
 * - 총판이 출판사에 주문할 때 출판사 내부 시스템에서 사용하는 식별 코드
 * - 예: OUP 'B00150000003', 비상교육 '02110' 등
 * - ISBN과 별개. ISBN은 국제 표준, publisher_code는 출판사 내부 코드
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // ISBN 다음에 배치 (조회 시 인접 컬럼)
            $table->string('publisher_code', 50)->nullable()->after('isbn')->comment('출판사 자체 도서코드');
            $table->index('publisher_code'); // 코드로 검색 가능
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['publisher_code']);
            $table->dropColumn('publisher_code');
        });
    }
};
