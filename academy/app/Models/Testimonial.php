<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasTranslations;

    protected array $translatable = ['body'];

    protected $fillable = ['name', 'role', 'body', 'rating', 'photo_path', 'is_approved', 'sort_order'];

    protected $casts = ['is_approved' => 'boolean', 'is_demo' => 'boolean'];
}
