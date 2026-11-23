<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Resource;
use App\Models\User;

/** Central server-side access rules for lessons and protected files. */
class AccessService
{
    public function canViewLesson(?User $user, Lesson $lesson): bool
    {
        $course = $lesson->course;

        if ($user && $user->canManageCourse($course)) {
            return true; // staff preview of drafts
        }
        if (! $course->isPublished() || ! $lesson->isPublished()) {
            return false;
        }
        if ($lesson->is_preview) {
            return true;
        }

        return $user !== null && $user->isEnrolledIn($course);
    }

    public function canDownloadResource(?User $user, Resource $resource): bool
    {
        if ($user?->isAdmin()) {
            return true;
        }
        if ($resource->status !== 'published') {
            return false;
        }
        if (! $resource->isRestricted()) {
            return true;
        }
        if (! $user || ! $resource->course) {
            return false;
        }

        return $user->canManageCourse($resource->course) || $user->isEnrolledIn($resource->course);
    }
}
