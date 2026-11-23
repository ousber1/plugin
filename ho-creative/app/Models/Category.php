<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasTranslations;

    protected $table = 'categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_published' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }


    public function scopePublished($q)
    {
        return $q->where('is_published', true);
    }
}
