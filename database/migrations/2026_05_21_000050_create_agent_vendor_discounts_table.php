<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_vendor_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_user_id')->comment('영업자')
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->comment('학원')
                ->constrained('vendors')->cascadeOnDelete();
            $table->decimal('discount_rate', 5, 2)->comment('기본 할인율(%)');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->unique(['agent_user_id', 'vendor_id'], 'uniq_agent_vendor');
            $table->index(['vendor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_vendor_discounts');
    }
};
