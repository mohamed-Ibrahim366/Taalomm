<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_submissions', 'attempt_number')) {
                $table->unsignedInteger('attempt_number')
                    ->default(1)
                    ->after('user_id');
            }

            if (!Schema::hasColumn('quiz_submissions', 'total_score')) {
                $table->unsignedInteger('total_score')
                    ->default(0)
                    ->after('score');
            }

            if (!Schema::hasColumn('quiz_submissions', 'submitted_at')) {
                $table->timestamp('submitted_at')
                    ->nullable()
                    ->after('feedback');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_submissions', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }

            if (Schema::hasColumn('quiz_submissions', 'total_score')) {
                $table->dropColumn('total_score');
            }

            if (Schema::hasColumn('quiz_submissions', 'attempt_number')) {
                $table->dropColumn('attempt_number');
            }
        });
    }
};
