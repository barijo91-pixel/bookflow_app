<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 50)->index()->comment('order.requested 등');
            $table->string('channel', 20)->comment('alimtalk/sms/push/email');
            $table->string('name', 100);
            $table->string('aligo_template_code', 50)->nullable()->comment('알리고 템플릿 코드');
            $table->string('subject', 200)->nullable()->comment('이메일/푸시 제목');
            $table->text('body');
            $table->json('variables')->nullable()->comment('치환 변수 목록');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_code', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
