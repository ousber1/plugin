<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'level' => ['nullable', 'in:beginner,intermediate,advanced,all'],
            'sort' => ['nullable', 'in:newest,oldest,popular,title'],
        ]);

        $categories = Category::ofType('course')->where('is_active', true)
            ->withCount(['courses' => fn ($q) => $q->published()])->get();
        $activeCategory = isset($validated['category']) ? $categories->firstWhere('slug', $validated['category']) : null;

        $courses = Course::published()->withCardData()
            ->searchTranslated(['title', 'subtitle', 'description'], $validated['q'] ?? null)
            ->when($activeCategory, fn ($q) => $q->where('category_id', $activeCategory->id))
            ->when(isset($validated['category']) && ! $activeCategory, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($validated['level'] ?? null, fn ($q, $level) => $q->where('level', $level));

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $courses->oldest('published_at'),
            'popular' => $courses->withCount('enrollments')->orderByDesc('enrollments_count'),
            'title' => $courses->orderBy('slug'),
            default => $courses->latest('published_at'),
        };

        return view('courses.index', [
            'courses' => $courses->paginate(12)->withQueryString(),
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'filters' => $validated,
            'totalPublished' => Course::published()->count(),
        ]);
    }

    public function show(Request $request, Course $course, ProgressService $progress)
    {
        $user = $request->user();
        abort_unless($course->isPublished() || ($user && $user->canManageCourse($course)), 404);

        $course->load([
            'category', 'instructor',
            'sections.lessons' => fn ($q) => $q->where('status', 'published'),
        ])->loadCount(['publishedLessons', 'approvedReviews', 'enrollments'])
            ->loadSum('publishedLessons', 'duration_seconds')->loadAvg('approvedReviews', 'rating');

        $enrolled = $user?->isEnrolledIn($course) ?? false;
        $state = [
            'enrolled' => $enrolled,
            'wishlisted' => $user ? $user->wishlist()->whereKey($course->id)->exists() : false,
            'progress' => $enrolled ? $progress->progress($user, $course) : null,
            'eligibility' => $enrolled ? $progress->eligibility($user, $course) : null,
            'completed' => $enrolled ? $progress->completedLessonIds($user, $course) : [],
            'request' => $user ? $user->certificateRequests()->where('course_id', $course->id)->first() : null,
            'myReview' => $user ? $user->reviews()->where('course_id', $course->id)->first() : null,
            'resumeLesson' => $enrolled ? $user->enrollments()->where('course_id', $course->id)->first()?->lastLesson : null,
        ];

        $reviews = $course->approvedReviews()->with('user:id,name')->latest()->limit(10)->get();
        $related = Course::published()->withCardData()->whereKeyNot($course->id)
            ->when($course->category_id, fn ($q) => $q->where('category_id', $course->category_id))
            ->latest('published_at')->limit(3)->get();

        return view('courses.show', compact('course', 'state', 'reviews', 'related'));
    }

    public function wishlist(Request $request, Course $course)
    {
        abort_unless($course->isPublished(), 404);
        $result = $request->user()->wishlist()->toggle($course->id);
        $added = count($result['attached']) > 0;

        return back()->with('success', $added ? __('أُضيفت الدورة إلى قائمتك المفضلة.') : __('أُزيلت الدورة من قائمتك المفضلة.'));
    }
}
