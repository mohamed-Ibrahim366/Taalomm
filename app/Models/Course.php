<?php

namespace App\Models;

use App\Enums\CourseLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'category_id',
        'title',
        'slug',
        'description',
        'thumbnail',
        'price',
        'currency',
        'duration',
        'level',
        'is_featured',
        'is_published',
    ];

    protected function casts(): array
    {
        return [

            'price'=>'decimal:2',

            'is_featured'=>'boolean',

            'is_published'=>'boolean',

            'level'=>CourseLevel::class,
        ];
    }

    public function teacher()
    {
        return $this->belongsTo(User::class,'teacher_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sections()
    {
        return $this->hasMany(CourseSection::class)
            ->orderBy('order');
    }

    public function lessons()
    {
        return $this->hasManyThrough(Lesson::class, CourseSection::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }


}
