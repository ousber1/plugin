<?php

namespace App\Http\Controllers\Admin;

use App\Models\Course;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class EnrollmentController extends AdminController
{
    public function index(Request $request, Course $course, ProgressService $progress)
    {
        $this->authorizeCourse($course);
        $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $enrollments = $course->enrollments()->with(['user:id,name,email', 'lastLesson'])
            ->when($request->input('q'), fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%")))
            ->latest()->paginate(30)->withQueryString();
        $rows = $enrollments->getCollection()->map(fn ($e) => ['e' => $e, 'p' => $progress->progress($e->user, $course)]);

        return view('admin.courses.enrollments', compact('course', 'enrollments', 'rows'));
    }
}
