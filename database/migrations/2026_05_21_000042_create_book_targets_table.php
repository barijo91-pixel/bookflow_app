<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('book_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('target_type', 30)->comment('grade/semester/level/audience/school');
            $table->string('code', 50)->comment('codes.code 참조 (group=target_type)');
            $table->timestamps();

            $table->unique(['book_id', 'target_type', 'code']);
            $table->index(['target_type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_targets');
    }
};
