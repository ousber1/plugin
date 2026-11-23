<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasTranslations;

    protected array $translatable = ['title'];

    protected $fillable = ['course_id', 'title', 'sort_order'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order')->orderBy('id');
    }
}
