<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 반품 관리 — 접수 → 확정(환불) 흐름.
 *
 *  returns        : 반품 1건 (주문 하나에 대해 여러 번 접수 가능)
 *  return_items   : 품목별 반품 수량 — 반품 단위는 "이 교재 몇 권"
 *  return_refunds : PG 부분취소 이력. 결제 건마다 한 줄, 재시도해도 성공 1줄만 남는다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 30)->unique()->comment('RT202608240001');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('vendor_id')->comment('학원');
            // 접수 시점의 담당자를 박아둔다 — 나중에 담당이 바뀌어도 반품 집계는 그대로
            $table->unsignedBigInteger('agent_user_id')->nullable();
            $table->unsignedBigInteger('distributor_user_id')->nullable();

            $table->string('status', 20)->default('requested')
                  ->comment('requested 접수 | confirmed 확정 | rejected 반려 | canceled 취소');
            $table->string('reason_code', 30)->default('other')
                  ->comment('damaged 파손 | wrong_book 오배송 | over_order 과다주문 | student_left 학생이탈 | other 기타');
            $table->string('reason_text', 500)->nullable();

            $table->unsignedInteger('total_qty')->default(0);
            $table->unsignedInteger('total_amount')->default(0)->comment('반품 금액 (주문 단가 기준)');

            // 환불 — 확정 시 PG 부분취소
            $table->string('refund_status', 20)->default('none')
                  ->comment('none 대상없음 | pending 대기 | partial 일부 | done 완료 | failed 실패');
            $table->unsignedInteger('refund_amount')->default(0)->comment('실제 PG 취소된 합계');
            $table->string('refund_error', 500)->nullable();

            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
            $table->index(['distributor_user_id', 'status']);
            $table->index(['agent_user_id', 'status']);
            $table->index('confirmed_at');
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('book_id')->nullable();
            $table->string('isbn_snapshot', 20)->nullable();
            $table->string('title_snapshot', 255)->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('unit_price')->default(0)->comment('주문 당시 단가');
            $table->unsignedInteger('line_total')->default(0);
            $table->timestamps();

            $table->index('return_id');
            $table->index('order_item_id');
            $table->index('book_id');
        });

        Schema::create('return_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('payment_request_id');
            $table->string('pg_payment_id', 100)->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->string('status', 20)->default('pending')->comment('pending | success | failed');
            $table->string('error_message', 500)->nullable();
            $table->text('response_json')->nullable();
            $table->timestamps();

            // 같은 반품·같은 결제 건에 두 줄이 생기지 않게 — 이중 취소 방지의 마지막 방어선
            $table->unique(['return_id', 'payment_request_id'], 'uniq_return_payment');
            $table->index('payment_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_refunds');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
