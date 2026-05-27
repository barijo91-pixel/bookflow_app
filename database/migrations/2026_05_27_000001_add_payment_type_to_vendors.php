<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // 결제 구분: 현매(cash) / 여신(credit). 기본 현매.
            $table->string('payment_type', 10)->default('cash')->after('bank_holder');
            // 여신 한도 (cash면 0)
            $table->unsignedInteger('credit_limit')->default(0)->after('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'credit_limit']);
        });
    }
};
