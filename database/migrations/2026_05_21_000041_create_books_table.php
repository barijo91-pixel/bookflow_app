<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 20)->unique()->comment('ISBN13');
            $table->string('title', 255)->index();
            $table->string('subtitle', 255)->nullable();
            $table->string('series_name', 150)->nullable()->index();
            $table->foreignId('publisher_id')->nullable()->constrained('publishers')->nullOnDelete();
            $table->string('subject_code', 30)->nullable()->index()->comment('과목분류 코드');
            $table->string('school_code', 30)->nullable()->comment('학교분류 (초/중/고/단행본)');
            $table->integer('price')->default(0)->comment('정가');
            $table->decimal('default_discount_rate', 5, 2)->default(0)->comment('도서 자체 기본 할인율(%)');
            $table->string('status_code', 20)->default('selling')->index()->comment('판매중/일시중지/절판/출간예정');
            $table->string('author', 150)->nullable();
            $table->string('spec', 100)->nullable()->comment('규격(예: 188*257mm)');
            $table->date('pub_date')->nullable();
            $table->string('edition', 50)->nullable()->comment('판/쇄 정보');
            $table->string('cover_path', 255)->nullable()->comment('대표 표지 경로');
            $table->string('attachment_path', 255)->nullable()->comment('대표 첨부 경로');
            $table->string('source', 20)->default('manual')->comment('aladin/yes24/manual/excel');
            $table->json('source_payload')->nullable()->comment('알라딘 원본 응답 등');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
