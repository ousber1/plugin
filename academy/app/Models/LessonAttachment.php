<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonAttachment extends Model
{
    protected $fillable = ['lesson_id', 'title', 'disk', 'path', 'original_name', 'mime', 'size'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
