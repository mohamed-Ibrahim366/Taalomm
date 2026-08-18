<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Mathematics',
                'slug' => 'mathematics',
                'icon' => 'math-icon',
                'description' => 'Mathematics courses for prep and secondary levels',
                'is_active' => true,
            ],
            [
                'name' => 'Physics',
                'slug' => 'physics',
                'icon' => 'physics-icon',
                'description' => 'Physics courses with experiments and theories',
                'is_active' => true,
            ],
            [
                'name' => 'Arabic',
                'slug' => 'arabic',
                'icon' => 'arabic-icon',
                'description' => 'Arabic grammar and literature courses',
                'is_active' => true,
            ],
            [
                'name' => 'English',
                'slug' => 'english',
                'icon' => 'english-icon',
                'description' => 'English language learning and writing courses',
                'is_active' => true,
            ],
        ];

            foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
