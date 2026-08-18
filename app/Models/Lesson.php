<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [

        'course_section_id',

        'title',

        'description',

        'video_url',

        'duration',

        'order',

        'is_preview',

    ];

    protected $casts = [

        'is_preview' => 'boolean',

    ];

    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }
}