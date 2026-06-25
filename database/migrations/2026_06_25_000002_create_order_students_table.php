<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 주문 대상 학생 (B2C 소매) — 도서주문 시 학급 선택 후 대상 학생 다중 지정.
     * 이름/학부모명은 스냅샷(주문 시점 보존). 학생/주문 삭제 시 함께 정리.
     */
    public function up(): void
    {
        Schema::create('order_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('student_name', 100)->nullable();
            $table->string('parent_name', 100)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_students');
    }
};
