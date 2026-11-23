<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolSuggestion extends Model
{
    protected $fillable = ['idea', 'name', 'email', 'status', 'admin_note'];
}
