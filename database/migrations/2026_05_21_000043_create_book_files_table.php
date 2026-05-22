<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('book_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('kind', 20)->comment('cover/preview/attachment');
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->string('mime', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['book_id', 'kind', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_files');
    }
};
