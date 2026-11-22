<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /** Free enrollment — no payment involved. Idempotent on repeat clicks. */
    public function store(Request $request, Course $course)
    {
        abort_unless($course->isPublished(), 404);
        $user = $request->user();

        try {
            Enrollment::firstOrCreate(['user_id' => $user->id, 'course_id' => $course->id]);
        } catch (UniqueConstraintViolationException) {
            // Already enrolled by a concurrent request.
        }

        $first = $course->lessons()->where('status', 'published')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->orderBy('sections.sort_order')->orderBy('sections.id')->orderBy('lessons.sort_order')->orderBy('lessons.id')
            ->select('lessons.*')->first();

        return redirect($first ? route('learn.show', [$course, $first]) : route('courses.show', $course))
            ->with('success', __('تم تسجيلك في الدورة مجانًا. بالتوفيق!'));
    }
}
