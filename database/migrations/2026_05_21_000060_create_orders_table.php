<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique()->comment('주문번호 (yyyyMMdd-NNNN)');
            $table->foreignId('vendor_id')->comment('주문자 학원')
                ->constrained('vendors')->restrictOnDelete();
            $table->foreignId('agent_user_id')->comment('담당 영업자')
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('distributor_user_id')->nullable()->comment('라우팅된 총판')
                ->constrained('users')->nullOnDelete();
            $table->string('status_code', 30)->default('requested')->index()
                ->comment('requested/confirmed/accepted/shipped/in_transit/completed/canceled/returned');
            $table->foreignId('ship_to_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('ship_to_address')->nullable();
            $table->string('ship_to_address_detail')->nullable();
            $table->string('ship_to_contact', 100)->nullable()->comment('수령인 + 연락처');
            $table->integer('subtotal_amount')->default(0)->comment('도서 합계 (할인 적용)');
            $table->integer('shipping_fee')->default(0);
            $table->integer('total_amount')->default(0);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('confirmed_at')->nullable()->comment('영업자 확정');
            $table->timestamp('accepted_at')->nullable()->comment('총판 접수');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'status_code']);
            $table->index(['agent_user_id', 'status_code']);
            $table->index(['distributor_user_id', 'status_code']);
            $table->index(['requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
