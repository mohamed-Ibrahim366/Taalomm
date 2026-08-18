<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'user_id',
        'attempt_number',
        'answers',
        'score',
        'total_score',
        'status',
        'feedback',
        'submitted_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'answers' => 'array',
        'score' => 'integer',
        'total_score' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
