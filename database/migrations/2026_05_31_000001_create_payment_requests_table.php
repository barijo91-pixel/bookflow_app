<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique(); // 학부모 접근용 URL 토큰
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('vendor_id'); // 학원 (FK 캐시)
            $table->unsignedBigInteger('class_id')->nullable(); // 학급
            $table->unsignedBigInteger('student_id')->nullable(); // 학생
            $table->unsignedBigInteger('parent_id')->nullable(); // 학부모
            $table->string('parent_name', 80)->nullable(); // 스냅샷
            $table->string('parent_phone', 20)->nullable();
            $table->string('student_name', 80)->nullable();
            $table->unsignedInteger('amount'); // 청구 금액
            $table->text('items_snapshot')->nullable(); // 도서 목록 (json)
            $table->string('status', 20)->default('pending'); // pending|sent|viewed|paid|expired|canceled
            $table->unsignedBigInteger('created_by'); // 학원 사용자
            $table->timestamp('sent_at')->nullable();   // 알림 발송 시각
            $table->timestamp('viewed_at')->nullable(); // 학부모가 결제 페이지 열어본 시각
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['parent_phone']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
