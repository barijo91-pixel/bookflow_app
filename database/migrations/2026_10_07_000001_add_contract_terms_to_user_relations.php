<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 총판–영업자 계약조건.
 *
 * 지금까지 매입율·분배율은 SettlementService 의 상수(63% / 6:4)로만 있어 모든 영업자에게
 * 똑같이 적용됐다. 실제로는 영업자마다 계약이 다를 수 있어 관계(user_relations)에 저장한다.
 *
 * 비워두면 기존처럼 서비스 기본값을 쓴다 → 기존 영업자는 영향 없음.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_relations', function (Blueprint $table) {
            // 총판 → 영업자 공급율 (정가 대비 %). 비우면 SettlementService::RATE_DIST_TO_AGENT
            $table->decimal('purchase_rate', 5, 2)->nullable()->after('status');
            // 마진 분배 (총판:영업자). 비우면 사이트 설정의 기본 분배율
            $table->string('split_ratio', 10)->nullable()->after('purchase_rate');
        });
    }

    public function down(): void
    {
        Schema::table('user_relations', function (Blueprint $table) {
            $table->dropColumn(['purchase_rate', 'split_ratio']);
        });
    }
};
