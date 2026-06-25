<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 주문에 학급(반) 연결 — 도서주문 시 선택(선택사항).
     * 학급 삭제 시 주문은 유지하고 class_id만 null 처리.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->after('vendor_id')
                ->constrained('academy_classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_id');
        });
    }
};
