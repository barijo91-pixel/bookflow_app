<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settlement_records', function (Blueprint $table) {
            $table->id();
            // 원천 거래
            $table->unsignedBigInteger('payment_request_id')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('vendor_id')->comment('학원');
            $table->unsignedBigInteger('agent_user_id')->nullable()->comment('담당 사입자');
            $table->unsignedBigInteger('distributor_user_id')->nullable()->comment('수금 총판');

            // 거래 금액
            $table->unsignedInteger('gross_amount')->default(0)->comment('정가 합계');
            $table->unsignedInteger('parent_paid')->default(0)->comment('학부모 결제 총액');
            $table->unsignedInteger('publisher_cost')->default(0)->comment('출판사 매입');
            $table->unsignedInteger('pg_fee')->default(0)->comment('PG 수수료');
            $table->unsignedInteger('booksys_fee')->default(0)->comment('BookSys 중계수수료');
            $table->unsignedInteger('shipping_fee')->default(0)->comment('배송비');

            // 마진 분배
            $table->integer('agent_margin')->default(0)->comment('사입자 명목 마진');
            $table->integer('agent_net')->default(0)->comment('사입자 실 마진 (학원우대 차감 후)');
            $table->integer('academy_bonus')->default(0)->comment('학원 도매단가 우대');
            $table->integer('dist_net')->default(0)->comment('총판 순이익');

            // 세무
            $table->string('agent_business_type', 30)->default('none');
            $table->unsignedInteger('agent_withholding_tax')->default(0)->comment('3.3% 원천징수');
            $table->unsignedInteger('agent_vat')->default(0)->comment('10% 부가세 가산');
            $table->integer('agent_payout')->default(0)->comment('사입자 실수령');

            // 분배 설정
            $table->string('split_ratio', 10)->default('6:4');
            $table->string('settle_type', 20)->default('b2c')->comment('b2c|b2b');

            // 상태 / 지급
            $table->string('status', 20)->default('computed')->comment('computed|paid_out|canceled');
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->unsignedBigInteger('paid_out_by')->nullable()->comment('지급 처리자 (총판)');
            $table->string('pg_transaction_id', 100)->nullable()->comment('PG 거래 ID (실연동 시)');

            // 추가 메타 (분배 디테일 JSON)
            $table->text('breakdown_json')->nullable();
            $table->text('memo')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['agent_user_id', 'status']);
            $table->index(['distributor_user_id', 'status']);
            $table->index(['order_id']);
            $table->index(['payment_request_id']);
            $table->index(['computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_records');
    }
};
