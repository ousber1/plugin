<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasTranslations;

    public const TYPES = ['video', 'text', 'quiz', 'exercise'];

    protected array $translatable = ['title', 'body'];

    protected $fillable = ['course_id', 'section_id', 'title', 'type', 'body', 'duration_seconds', 'is_preview', 'is_required', 'status', 'sort_order'];

    protected $casts = ['is_preview' => 'boolean', 'is_required' => 'boolean'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function attachments()
    {
        return $this->hasMany(LessonAttachment::class);
    }

    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function hasVideo(): bool
    {
        return $this->video_path !== null;
    }

    /** A preview lesson is playable by anyone when it and its course are published. */
    public function isPublicPreview(): bool
    {
        return $this->is_preview && $this->isPublished() && $this->course->isPublished();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'video' => __('فيديو'),
            'text' => __('درس نصي'),
            'quiz' => __('اختبار'),
            'exercise' => __('تمرين'),
            default => $this->type,
        };
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            'video' => 'play', 'text' => 'file-text', 'quiz' => 'check-square', default => 'download',
        };
    }

    public function durationLabel(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }
        $m = intdiv($this->duration_seconds, 60);
        $s = $this->duration_seconds % 60;

        return sprintf('%02d:%02d', $m, $s);
    }
}
