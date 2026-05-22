<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()
                ->constrained('academy_classes')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('parents')->nullOnDelete();
            $table->string('name', 100);
            $table->string('grade_code', 30)->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
