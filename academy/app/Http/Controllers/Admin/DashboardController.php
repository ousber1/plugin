<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;

class DashboardController extends AdminController
{
    public function index()
    {
        $user = auth()->user();
        $courseIds = $user->isAdmin() ? null : $this->managedCourseIds();

        $kpis = [
            'courses' => Course::when($courseIds !== null, fn ($q) => $q->whereIn('id', $courseIds))->count(),
            'published' => Course::published()->when($courseIds !== null, fn ($q) => $q->whereIn('id', $courseIds))->count(),
            'enrollments' => Enrollment::when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds))->count(),
            'completions' => Enrollment::whereNotNull('completed_at')->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds))->count(),
        ];
        if ($user->isAdmin()) {
            $kpis['students'] = User::where('role', 'student')->count();
            $kpis['pending_certs'] = CertificateRequest::where('status', CertificateStatus::PendingContact)->count();
            $kpis['in_progress_certs'] = CertificateRequest::whereIn('status', [CertificateStatus::Received, CertificateStatus::Preparing])->count();
        }

        $recentEnrollments = Enrollment::with(['user:id,name', 'course:id,slug,title'])
            ->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds))->latest()->limit(8)->get();
        $recentRequests = $user->isAdmin() ? CertificateRequest::with(['user:id,name', 'course:id,slug,title'])->latest()->limit(6)->get() : collect();
        $activity = LessonProgress::when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds))
            ->where('completed_at', '>=', now()->subDays(7))->count();

        return view('admin.dashboard', compact('kpis', 'recentEnrollments', 'recentRequests', 'activity'));
    }

    private function managedCourseIds(): array
    {
        $u = auth()->user();

        return Course::where('instructor_id', $u->id)->pluck('id')->merge($u->assignedCourses()->pluck('courses.id'))->unique()->all();
    }
}
