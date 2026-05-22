<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('courier_code', 30)->nullable()->comment('택배사 코드');
            $table->string('tracking_no', 50)->nullable()->index();
            $table->string('ship_status_code', 20)->default('preparing')
                ->comment('preparing/shipped/in_transit/delivered/failed');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipments');
    }
};
