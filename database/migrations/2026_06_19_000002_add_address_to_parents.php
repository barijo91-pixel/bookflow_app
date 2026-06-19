<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 학부모 주소 추가 — 소매(개별배송) 시 학생 가정으로 배송하기 위한 배송지.
 * 총판 배송접수 단계에서 사용.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->after('email');
            $table->string('address_detail', 100)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->dropColumn(['address', 'address_detail']);
        });
    }
};
