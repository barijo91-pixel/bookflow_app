<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academy_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name', 100)->comment('반 이름');
            $table->string('grade_code', 30)->nullable()->comment('학년 코드');
            $table->foreignId('teacher_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('status', 20)->default('active')->comment('active/closed');
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_classes');
    }
};
