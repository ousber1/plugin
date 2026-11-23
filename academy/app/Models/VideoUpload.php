<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VideoUpload extends Model
{
    use HasUuids;

    protected $fillable = ['lesson_id', 'user_id', 'original_name', 'total_size', 'received_bytes', 'status'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function tempPath(): string
    {
        return storage_path('app/private/chunks/'.$this->id.'.part');
    }
}
