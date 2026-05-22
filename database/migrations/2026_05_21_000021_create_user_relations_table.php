<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->comment('총판 or 영업자')
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_user_id')->comment('영업자 or 학원담당')
                ->constrained('users')->cascadeOnDelete();
            $table->string('relation_type', 30)->comment('distributor_agent / agent_academy');
            $table->string('status', 20)->default('active')->comment('active/terminated');
            $table->date('started_at');
            $table->date('terminated_at')->nullable();
            $table->foreignId('terminated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->unique(['parent_user_id', 'child_user_id', 'relation_type'], 'uniq_user_relation');
            $table->index(['relation_type', 'status']);
            $table->index(['child_user_id', 'relation_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_relations');
    }
};
