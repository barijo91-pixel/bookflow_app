<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_vendor_book_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->decimal('discount_rate', 5, 2)->comment('교재별 오버라이드 할인율(%)');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->unique(['agent_user_id', 'vendor_id', 'book_id'], 'uniq_agent_vendor_book');
            $table->index(['book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_vendor_book_discounts');
    }
};
