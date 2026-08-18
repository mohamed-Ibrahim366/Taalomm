<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (!Schema::hasColumn('quizzes', 'course_id')) {
                $table->foreignId('course_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('quizzes', 'lesson_id')) {
                $table->foreignId('lesson_id')
                    ->nullable()
                    ->after('course_section_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('quizzes', 'max_attempts')) {
                $table->unsignedInteger('max_attempts')
                    ->default(1)
                    ->after('time_limit');
            }

            if (!Schema::hasColumn('quizzes', 'is_published')) {
                $table->boolean('is_published')
                    ->default(true)
                    ->after('max_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'is_published')) {
                $table->dropColumn('is_published');
            }

            if (Schema::hasColumn('quizzes', 'max_attempts')) {
                $table->dropColumn('max_attempts');
            }

            if (Schema::hasColumn('quizzes', 'lesson_id')) {
                $table->dropConstrainedForeignId('lesson_id');
            }

            if (Schema::hasColumn('quizzes', 'course_id')) {
                $table->dropConstrainedForeignId('course_id');
            }
        });
    }
};
