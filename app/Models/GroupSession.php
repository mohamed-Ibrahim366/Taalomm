<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupSession extends Model
{
    use HasFactory;

    protected $table = 'group_schedules';

    protected $fillable = [
        'group_id',
        'day',
        'start_time',
        'end_time',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
