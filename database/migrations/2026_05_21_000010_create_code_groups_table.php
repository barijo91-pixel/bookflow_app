<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('code_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 50)->unique()->comment('그룹코드');
            $table->string('name', 100)->comment('그룹명');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('시스템 그룹(삭제 불가)');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_groups');
    }
};
