<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('codes', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 50)->comment('code_groups.group_code 참조');
            $table->string('code', 50)->comment('세부 코드');
            $table->string('name', 100)->comment('세부 이름');
            $table->string('value', 255)->nullable()->comment('부가값(요율 등)');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_code', 'code']);
            $table->index(['group_code', 'is_active', 'sort_order']);
            $table->foreign('group_code')->references('group_code')->on('code_groups')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes');
    }
};
