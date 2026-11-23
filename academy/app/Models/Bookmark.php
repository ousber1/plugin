<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    protected $fillable = ['user_id', 'lesson_id'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
