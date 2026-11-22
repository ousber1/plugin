<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['name', 'url', 'logo_path', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];
}
