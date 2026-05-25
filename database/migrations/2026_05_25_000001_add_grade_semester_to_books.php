<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('grade_code', 20)->nullable()->after('school_code')
                  ->comment('학년: pre_e/e1~e6/m1~m3/h1~h3');
            $table->string('semester_code', 10)->nullable()->after('grade_code')
                  ->comment('학기: s1(1학기)/s2(2학기)');
            $table->index(['school_code', 'grade_code']);
            $table->index('semester_code');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['school_code', 'grade_code']);
            $table->dropIndex(['semester_code']);
            $table->dropColumn(['grade_code', 'semester_code']);
        });
    }
};
