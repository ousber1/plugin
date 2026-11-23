<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'locale', 'headline', 'bio', 'avatar_path'];

    protected $hidden = ['password', 'remember_token'];

    /** In-memory defaults matching the database defaults. */
    protected $attributes = ['role' => 'student', 'is_active' => true, 'is_demo' => false];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isInstructor(): bool
    {
        return $this->role === Role::Instructor;
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isInstructor();
    }

    /** Courses an instructor is assigned to manage. */
    public function assignedCourses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function canManageCourse(Course $course): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->isInstructor()
            && ($course->instructor_id === $this->id || $course->assignees()->whereKey($this->id)->exists());
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'enrollments')->withPivot(['last_lesson_id', 'last_activity_at', 'completed_at'])->withTimestamps();
    }

    public function isEnrolledIn(Course $course): bool
    {
        return $this->enrollments()->where('course_id', $course->id)->exists();
    }

    public function certificateRequests()
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function wishlist()
    {
        return $this->belongsToMany(Course::class, 'wishlists')->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? \App\Support\Media::url($this->avatar_path) : null;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1));
    }
}
