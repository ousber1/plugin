<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Level;
use App\Models\Category;
use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\User;
use App\Support\Audit;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CourseController extends AdminController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:draft,published'], 'category' => ['nullable', 'integer']]);

        $courses = Course::query()->with(['category', 'instructor:id,name'])
            ->withCount(['lessons', 'enrollments'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('instructor_id', $user->id)->orWhereHas('assignees', fn ($a) => $a->whereKey($user->id))))
            ->searchTranslated(['title', 'slug'], $request->input('q'))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.courses.index', ['courses' => $courses, 'categories' => Category::ofType('course')->get()]);
    }

    public function create()
    {
        return view('admin.courses.form', ['course' => new Course(['level' => 'beginner', 'language' => 'ar']), ...$this->formData()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $course = new Course;
        $this->fill($course, $request, $data);
        $course->status = 'draft';
        $course->save();
        $this->syncAssignees($course, $request);
        Audit::log('course.created', $course, ['title' => $course->tr('title', 'ar')]);

        return redirect()->route('admin.courses.curriculum', $course)->with('success', 'تم إنشاء الدورة كمسودة. أضف الأقسام والدروس ثم انشرها.');
    }

    public function edit(Course $course)
    {
        $this->authorizeCourse($course);

        return view('admin.courses.form', ['course' => $course->load('assignees'), ...$this->formData()]);
    }

    public function update(Request $request, Course $course)
    {
        $this->authorizeCourse($course);
        $data = $this->validated($request, $course);
        $this->fill($course, $request, $data);
        $course->save();
        if ($request->user()->isAdmin()) {
            $this->syncAssignees($course, $request);
        }

        return back()->with('success', 'تم حفظ الدورة.');
    }

    public function publish(Request $request, Course $course)
    {
        $this->authorizeCourse($course);
        if ($course->status === 'published') {
            $course->status = 'draft';
            $course->save();
            Audit::log('course.unpublished', $course);

            return back()->with('success', 'أصبحت الدورة مسودة ولم تعد ظاهرة للزوار.');
        }
        if (! $course->lessons()->where('status', 'published')->exists()) {
            return back()->with('error', 'لا يمكن نشر دورة بدون درس منشور واحد على الأقل.');
        }
        $course->status = 'published';
        $course->published_at = $course->published_at && $course->published_at->lte(now()) ? $course->published_at : now();
        $course->save();
        Audit::log('course.published', $course);

        return back()->with('success', 'تم نشر الدورة وأصبحت ظاهرة في الكتالوج.');
    }

    public function destroy(Course $course)
    {
        if ($course->enrollments()->exists() || $course->hasMany(CertificateRequest::class)->exists()) {
            return back()->with('error', 'لا يمكن حذف دورة بها طلاب مسجلون أو طلبات شهادات. ألغِ نشرها بدلًا من ذلك.');
        }
        foreach ($course->lessons as $lesson) {
            if ($lesson->video_path) {
                Storage::disk($lesson->video_disk)->delete($lesson->video_path);
            }
            foreach ($lesson->attachments as $a) {
                Storage::disk($a->disk)->delete($a->path);
            }
        }
        if ($course->thumbnail_path) {
            Storage::disk('public')->delete($course->thumbnail_path);
        }
        Audit::log('course.deleted', $course, ['title' => $course->tr('title', 'ar')]);
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'تم حذف الدورة.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::ofType('course')->get(),
            'instructors' => User::whereIn('role', ['admin', 'instructor'])->orderBy('name')->get(['id', 'name', 'role']),
        ];
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $isAdmin = $request->user()->isAdmin();

        return $request->validate([
            ...$this->trRules('title', true, 190),
            ...$this->trRules('subtitle', false, 300),
            ...$this->trRules('description', false, 60000),
            ...$this->trRules('outcomes', false, 5000),
            ...$this->trRules('prerequisites', false, 5000),
            ...$this->trRules('audience', false, 5000),
            ...$this->trRules('seo_title', false, 190),
            ...$this->trRules('seo_description', false, 320),
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('courses', 'slug')->ignore($course?->id)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('type', 'course')],
            'instructor_id' => [$isAdmin ? 'nullable' : 'prohibited', Rule::exists('users', 'id')->whereIn('role', ['admin', 'instructor'])],
            'assignees' => [$isAdmin ? 'nullable' : 'prohibited', 'array'],
            'assignees.*' => [Rule::exists('users', 'id')->where('role', 'instructor')],
            'level' => ['required', Rule::enum(Level::class)],
            'language' => ['required', Rule::in(Locales::codes())],
            'thumbnail' => ['nullable', 'image', 'mimes:'.config('academy.images.mimes'), 'max:'.config('academy.images.max_kb')],
            'remove_thumbnail' => ['nullable', 'boolean'],
            'is_featured' => [$isAdmin ? 'nullable' : 'prohibited', 'boolean'],
            'featured_order' => [$isAdmin ? 'nullable' : 'prohibited', 'integer', 'min:0', 'max:9999'],
            'requires_quiz_pass' => ['nullable', 'boolean'],
        ]);
    }

    private function fill(Course $course, Request $request, array $data): void
    {
        $course->fill([
            'title' => $this->trInput($request, 'title'),
            'subtitle' => $this->trInput($request, 'subtitle'),
            'description' => $this->trInput($request, 'description', 'rich'),
            'outcomes' => $this->trInput($request, 'outcomes', 'list'),
            'prerequisites' => $this->trInput($request, 'prerequisites', 'list'),
            'audience' => $this->trInput($request, 'audience', 'list'),
            'seo_title' => $this->trInput($request, 'seo_title'),
            'seo_description' => $this->trInput($request, 'seo_description'),
            'category_id' => $data['category_id'] ?? null,
            'level' => $data['level'],
            'language' => $data['language'],
            'requires_quiz_pass' => $request->boolean('requires_quiz_pass'),
        ]);
        $course->slug = $data['slug'] ?? null ?: ($course->slug ?: $this->uniqueSlug(Course::class, $data['title']['en'] ?? '' ?: ($data['title']['fr'] ?? '' ?: $data['title']['ar']), $course->id));
        if ($request->user()->isAdmin()) {
            $course->instructor_id = $data['instructor_id'] ?? null;
            $course->is_featured = $request->boolean('is_featured');
            $course->featured_order = (int) ($data['featured_order'] ?? 0);
        } elseif (! $course->exists) {
            $course->instructor_id = $request->user()->id;
        }
        if ($request->boolean('remove_thumbnail') && $course->thumbnail_path) {
            Storage::disk('public')->delete($course->thumbnail_path);
            $course->thumbnail_path = null;
        }
        if ($request->hasFile('thumbnail')) {
            $old = $course->thumbnail_path;
            $course->thumbnail_path = \App\Support\ImageOptimizer::store($request->file('thumbnail'), 'courses');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }
    }

    private function syncAssignees(Course $course, Request $request): void
    {
        $course->assignees()->sync($request->input('assignees', []));
    }
}
