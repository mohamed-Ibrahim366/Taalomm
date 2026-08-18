<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'lesson_id',
        'course_section_id',
        'title',
        'description',
        'passing_score',
        'time_limit',
        'max_attempts',
        'is_published',
        'questions',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'lesson_id' => 'integer',
        'course_section_id' => 'integer',
        'passing_score' => 'integer',
        'time_limit' => 'integer',
        'max_attempts' => 'integer',
        'is_published' => 'boolean',
        'questions' => 'array',
    ];

    protected $attributes = [
        'max_attempts' => 1,
        'is_published' => true,
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function courseSection()
    {
        return $this->section();
    }

    public function submissions()
    {
        return $this->hasMany(QuizSubmission::class);
    }
}
