<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 50)->index();
            $table->string('channel', 20)->index();
            $table->string('recipient_type', 20)->comment('user/parent/raw');
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('payload')->nullable()->comment('렌더링된 본문');
            $table->json('context')->nullable()->comment('치환 변수값');
            $table->string('status', 20)->default('queued')->index()
                ->comment('queued/sent/failed/skipped');
            $table->string('provider', 30)->nullable()->comment('aligo/fcm/ses 등');
            $table->string('provider_message_id', 100)->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
