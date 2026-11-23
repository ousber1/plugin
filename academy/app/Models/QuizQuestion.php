<?php

namespace App\Models;

use App\Casts\Json;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = ['quiz_id', 'question', 'options', 'correct', 'explanation', 'sort_order'];

    protected $casts = ['options' => Json::class, 'correct' => Json::class];

    public function isMultiple(): bool
    {
        return count($this->correct ?? []) > 1;
    }
}
