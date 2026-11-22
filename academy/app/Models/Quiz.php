<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = ['course_id', 'lesson_id', 'pass_score'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function bestAttemptFor(User $user): ?QuizAttempt
    {
        return $this->attempts()->where('user_id', $user->id)->orderByDesc('score')->first();
    }

    public function passedBy(User $user): bool
    {
        return $this->attempts()->where('user_id', $user->id)->where('passed', true)->exists();
    }
}
