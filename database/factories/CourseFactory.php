<?php

namespace Database\Factories;

use App\Enums\CourseLevel;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);
        return [
            'teacher_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->faker->paragraph(),
            'thumbnail' => null,
            'price' => $this->faker->randomFloat(2, 50, 500),
            'currency' => 'EGP',
            'duration' => $this->faker->numberBetween(10, 100),
            'level' => $this->faker->randomElement(CourseLevel::cases()),
            'is_featured' => false,
            'is_published' => false,
        ];
    }
}
