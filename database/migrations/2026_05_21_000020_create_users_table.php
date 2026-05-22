<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique()->comment('로그인 ID(이메일 형식)');
            $table->string('password');
            $table->string('name', 100);
            $table->string('phone', 20)->index();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('role_code', 30)->index()->comment('admin/distributor/agent/academy');
            $table->string('admin_level', 20)->nullable()->comment('super/staff (role_code=admin일 때)');
            $table->string('status_code', 20)->default('pending')->index()->comment('pending/active/suspended/terminated');
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('address_detail')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
