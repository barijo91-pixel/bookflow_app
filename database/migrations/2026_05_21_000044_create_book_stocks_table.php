<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('book_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('distributor_user_id')->comment('총판 user.id')
                ->constrained('users')->cascadeOnDelete();
            $table->integer('qty')->default(0);
            $table->integer('low_stock_threshold')->default(0);
            $table->integer('reserved_qty')->default(0)->comment('주문 예약 수량');
            $table->timestamps();

            $table->unique(['book_id', 'distributor_user_id']);
            $table->index(['distributor_user_id', 'qty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_stocks');
    }
};
