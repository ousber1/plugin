<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasTranslations;

    protected $table = 'attachments';

    protected $guarded = ['id'];

    public function attachable()
    {
        return $this->morphTo();
    }
}
