<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 사입자(영업자) 세무 정보 컬럼 추가 — 계획서 8-A장
 *
 * business_type:
 *   - none: 비사업자 (N잡·알바) → 3.3% 원천징수 적용
 *   - individual_simple: 개인사업자 (간이과세) → 연매출 8천만 미만
 *   - individual_general: 개인사업자 (일반과세) → 8천만 이상
 *   - corporate: 법인
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('business_type', 30)->default('none')->after('phone')
                ->comment('사업자 유형: none|individual_simple|individual_general|corporate');
            $table->string('business_no', 20)->nullable()->after('business_type')
                ->comment('사업자등록번호 (000-00-00000)');
            $table->string('business_name', 100)->nullable()->after('business_no')
                ->comment('상호 (사업자등록증 상의)');
            // 누적 수수료 (정산 시 자동 갱신)
            $table->bigInteger('annual_commission')->default(0)->after('business_name')
                ->comment('당해년도 누적 수수료 (원)');
            $table->date('commission_year')->nullable()->after('annual_commission')
                ->comment('annual_commission 기준 연도 시작일 (1월 1일)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'business_no', 'business_name', 'annual_commission', 'commission_year']);
        });
    }
};
