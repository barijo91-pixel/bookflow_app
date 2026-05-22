<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index()->comment('학원명/거래처명');
            $table->string('owner_name', 100)->nullable()->comment('대표자');
            $table->string('business_no', 20)->nullable()->index()->comment('사업자번호');
            $table->string('type_code', 30)->default('academy')->comment('vendor_type 코드 (academy 등)');
            $table->string('status_code', 20)->default('active')->index()->comment('정상/일시정지/거래종료');
            $table->string('biz_type', 100)->nullable()->comment('업태');
            $table->string('biz_item', 100)->nullable()->comment('종목');
            $table->string('mobile', 20)->nullable();
            $table->string('tel', 20)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('address_detail')->nullable();
            $table->string('bank_code', 10)->nullable()->comment('은행코드');
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_holder', 50)->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
