<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use HasTranslations;

    protected $table = 'document_sequences';

    protected $guarded = ['id'];
}
