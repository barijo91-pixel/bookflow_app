<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 결제 건에 PG 결제 식별자를 남긴다.
 *
 * 지금까지 PortOne paymentId 는 소매만 settlement_records.pg_transaction_id 에 남고
 * 도매(학원 직접결제)는 audit_logs 안에만 있었다.
 * 반품 환불(부분취소)은 결제 건에서 곧장 paymentId 를 찾아야 하므로 여기로 끌어온다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->string('pg_payment_id', 100)->nullable()->after('status')
                  ->comment('PortOne paymentId — 환불(부분취소)용');
            $table->unsignedInteger('refunded_amount')->default(0)->after('pg_payment_id')
                  ->comment('이 결제에서 이미 환불된 누계');
            $table->index('pg_payment_id');
        });

        // 소매 — 정산 레코드에 남아 있던 값 회수
        DB::statement("
            UPDATE payment_requests pr
              JOIN settlement_records sr ON sr.payment_request_id = pr.id
               SET pr.pg_payment_id = sr.pg_transaction_id
             WHERE pr.pg_payment_id IS NULL
               AND sr.pg_transaction_id IS NOT NULL
               AND sr.pg_transaction_id <> ''
        ");

        // 도매 — 감사로그(payment_id) 에서 회수. 주문당 직접결제는 1건이라 order_id 로 잇는다.
        foreach (DB::table('audit_logs')
                    ->where('action', 'wholesale_direct_pay')
                    ->orderBy('id')
                    ->get(['entity_id', 'after']) as $log) {
            $meta = json_decode((string) $log->after, true);
            $pid  = $meta['payment_id'] ?? null;
            if (! $pid || ($meta['mock'] ?? false)) continue;

            DB::table('payment_requests')
                ->where('order_id', $log->entity_id)
                ->whereNull('pg_payment_id')
                ->where('status', 'paid')
                ->update(['pg_payment_id' => $pid]);
        }
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropIndex(['pg_payment_id']);
            $table->dropColumn(['pg_payment_id', 'refunded_amount']);
        });
    }
};
