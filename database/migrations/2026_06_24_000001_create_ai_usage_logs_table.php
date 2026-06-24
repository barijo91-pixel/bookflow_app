<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * AI(Claude) 호출 사용량/비용 추적 — 사용자별 집계·한도·과금 기반.
     * 책 사진 인식(book_recognition) 등 외부 LLM 호출마다 1행 기록.
     */
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('호출 사용자');
            $table->string('type', 40)->default('book_recognition')->comment('호출 유형');
            $table->string('model', 50)->nullable()->comment('사용 모델');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->integer('est_cost_krw')->default(0)->comment('추정 비용(원)');
            $table->string('status', 20)->default('success')->comment('success|error|skipped');
            $table->text('meta_json')->nullable()->comment('요청/응답 메타');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
