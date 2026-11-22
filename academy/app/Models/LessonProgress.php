<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = ['user_id', 'lesson_id', 'course_id', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];
}
