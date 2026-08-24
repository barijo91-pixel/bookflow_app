<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 랜딩에 노출하는 대표 이용학원.
 *
 * vendors(실제 거래처)와 분리한다 — 이쪽은 홍보용 큐레이션이라
 * 노출 순서·로고·지역 표기를 마케팅 편의대로 잡아야 하고,
 * 거래처의 사업자·정산 정보와 수명주기가 다르다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('featured_academies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->comment('학원명 — 노출되는 이름');
            $table->unsignedBigInteger('region_id')->nullable()->comment('시·도 (regions.level=sido) — 지역탭 기준');
            $table->string('city', 60)->nullable()->comment('시군구 등 세부 표기 (예: 해운대구)');
            $table->string('logo_path', 255)->nullable()->comment('로고·간판 이미지. 없으면 이름만 표시');
            $table->string('homepage_url', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'region_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_academies');
    }
};
