<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 주문을 실제로 올린 사용자.
 * 학원이 직접 올리면 학원 계정, 영업자가 대행하면 영업자 계정이 들어간다.
 * (agent_user_id 는 "담당 영업자"라 대행 여부를 구분할 수 없어 별도로 둔다)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('distributor_user_id');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};
