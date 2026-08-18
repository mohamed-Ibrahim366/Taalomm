<?php

use App\Enums\CourseLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {

            $table->id();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            $table->string('slug')->unique();

            $table->text('description');

            $table->string('thumbnail')->nullable();

            $table->decimal('price', 10, 2)->default(0);

            $table->string('currency', 10)->default('EGP');

            $table->integer('duration')->default(0);

            $table->enum('level', [
                CourseLevel::Beginner->value,
                CourseLevel::Intermediate->value,
                CourseLevel::Advanced->value,
            ]);

            $table->boolean('is_featured')->default(false);

            $table->boolean('is_published')->default(false);

            $table->timestamps();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
