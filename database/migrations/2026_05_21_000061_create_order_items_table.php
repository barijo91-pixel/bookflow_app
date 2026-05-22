<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
            $table->string('isbn_snapshot', 20)->nullable();
            $table->string('title_snapshot', 255)->nullable();
            $table->integer('qty');
            $table->integer('list_price')->comment('정가 스냅샷');
            $table->decimal('discount_rate', 5, 2)->comment('적용 할인율(%)');
            $table->string('discount_source', 20)->comment('default/override/book');
            $table->integer('unit_price')->comment('최종 단가');
            $table->integer('line_total');
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
