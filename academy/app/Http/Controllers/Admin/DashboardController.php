<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Models\CertificateRequest;
use App\Models\ContactMessage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Review;
use App\Models\ToolSuggestion;
use App\Models\User;
use App\Services\QuizGenerator;
use App\Support\Settings;
use Carbon\CarbonImmutable;

class DashboardController extends AdminController
{
    public function index()
    {
        $user = auth()->user();
        $ids = $user->isAdmin() ? null : $this->managedCourseIds();
        $scope = fn ($q, string $col = 'course_id') => $ids === null ? $q : $q->whereIn($col, $ids);

        $now = CarbonImmutable::now();
        $from = $now->subDays(29)->startOfDay();
        $prevFrom = $from->subDays(30);

        $count = fn ($query, $start, $end = null) => (clone $query)->where('created_at', '>=', $start)->when($end, fn ($q) => $q->where('created_at', '<', $end))->count();

        $enrollQ = $scope(Enrollment::query());
        $progressQ = $scope(LessonProgress::query()->whereNotNull('completed_at'));
        $kpis = [
            'enrollments' => $this->trend($count($enrollQ, $from), $count($enrollQ, $prevFrom, $from)),
            'completions' => $this->trend(
                (clone $enrollQ)->where('completed_at', '>=', $from)->count(),
                (clone $enrollQ)->where('completed_at', '>=', $prevFrom)->where('completed_at', '<', $from)->count()
            ),
            'lessons_done' => $this->trend($count($progressQ, $from), $count($progressQ, $prevFrom, $from)),
        ];
        $totalEnroll = (clone $enrollQ)->count();
        $completionRate = $totalEnroll ? (int) round((clone $enrollQ)->whereNotNull('completed_at')->count() * 100 / $totalEnroll) : 0;

        $courses = [
            'total' => $scope(Course::query(), 'id')->count(),
            'published' => $scope(Course::published(), 'id')->count(),
            'lessons' => $scope(Lesson::where('status', 'published'))->count(),
            'hours' => round($scope(Lesson::where('status', 'published'))->sum('duration_seconds') / 3600, 1),
            'enrollments' => $totalEnroll,
        ];

        $totals = [];
        $todo = [];
        if ($user->isAdmin()) {
            $studentQ = User::where('role', 'student');
            $certQ = CertificateRequest::query();
            $kpis['students'] = $this->trend($count($studentQ, $from), $count($studentQ, $prevFrom, $from));
            $kpis['certificates'] = $this->trend($count($certQ, $from), $count($certQ, $prevFrom, $from));
            $totals = ['students' => (clone $studentQ)->count(), 'certificates' => CertificateRequest::count()];
            $todo = [
                ['n' => CertificateRequest::where('status', CertificateStatus::PendingContact)->count(), 'label' => 'طلبات شهادة بانتظار التواصل', 'icon' => 'whatsapp', 'url' => route('admin.certificate-requests.index', ['status' => 'pending_contact'])],
                ['n' => CertificateRequest::whereIn('status', [CertificateStatus::Received, CertificateStatus::Preparing])->count(), 'label' => 'شهادات قيد التجهيز', 'icon' => 'truck', 'url' => route('admin.certificate-requests.index', ['status' => 'preparing'])],
                ['n' => Review::where('is_approved', false)->count(), 'label' => 'مراجعات بانتظار الاعتماد', 'icon' => 'star', 'url' => route('admin.reviews.index', ['status' => 'pending'])],
                ['n' => ContactMessage::whereNull('read_at')->count(), 'label' => 'رسائل غير مقروءة', 'icon' => 'mail', 'url' => route('admin.messages.index')],
                ['n' => ToolSuggestion::where('status', 'pending')->count(), 'label' => 'اقتراحات أدوات جديدة', 'icon' => 'sparkles', 'url' => route('admin.tools.index', ['status' => 'pending'])],
            ];
        }

        // Daily series for the last 30 days.
        $days = collect(range(0, 29))->map(fn ($i) => $from->addDays($i)->toDateString());
        $daily = fn ($query) => (clone $query)->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $enrollDaily = $daily($enrollQ);
        $series = [
            ['key' => 'enrollments', 'label' => 'تسجيلات في الدورات', 'color' => '#4b37c4', 'values' => $days->map(fn ($d) => (int) ($enrollDaily[$d] ?? 0))->all()],
        ];
        if ($user->isAdmin()) {
            $userDaily = $daily(User::where('role', 'student'));
            $series[] = ['key' => 'students', 'label' => 'طلاب جدد', 'color' => '#d99a0b', 'values' => $days->map(fn ($d) => (int) ($userDaily[$d] ?? 0))->all()];
        }

        $topCourses = $scope(Course::query(), 'id')
            ->withCount(['enrollments', 'enrollments as completed_count' => fn ($q) => $q->whereNotNull('completed_at')])
            ->orderByDesc('enrollments_count')->limit(6)->get()
            ->filter(fn ($c) => $c->enrollments_count > 0)
            ->map(fn ($c) => ['label' => $c->tr('title'), 'value' => $c->enrollments_count, 'note' => (int) round($c->completed_count * 100 / max(1, $c->enrollments_count)).'% إتمام', 'url' => route('admin.courses.enrollments', $c)])
            ->values()->all();

        $certStatus = $user->isAdmin()
            ? collect(CertificateStatus::cases())->map(fn ($s) => ['label' => $s->label(), 'value' => CertificateRequest::where('status', $s)->count(), 'url' => route('admin.certificate-requests.index', ['status' => $s->value])])->all()
            : [];

        $byCenter = $user->isAdmin() && \App\Models\Center::hasPublic()
            ? \App\Models\Center::active()->withCount('students')->get()
                ->map(fn ($c) => ['label' => $c->tr('city').' — '.$c->tr('name'), 'value' => $c->students_count, 'url' => route('admin.users.index', ['center' => $c->id])])->all()
            : [];

        return view('admin.dashboard', [
            'byCenter' => $byCenter,
            'kpis' => $kpis, 'totals' => $totals, 'todo' => $todo, 'courses' => $courses, 'completionRate' => $completionRate,
            'days' => $days->all(), 'series' => $series, 'topCourses' => $topCourses, 'certStatus' => $certStatus,
            'activity' => $this->activity($ids), 'health' => $user->isAdmin() ? $this->health() : [],
        ]);
    }

    private function trend(int $current, int $previous): array
    {
        $delta = $previous ? (int) round(($current - $previous) * 100 / $previous) : null;

        return ['value' => $current, 'previous' => $previous, 'delta' => $delta];
    }

    /** Latest events across the academy, newest first. */
    private function activity(?array $ids): array
    {
        $items = collect();
        Enrollment::with(['user:id,name', 'course:id,slug,title'])->when($ids !== null, fn ($q) => $q->whereIn('course_id', $ids))->latest()->limit(8)->get()
            ->each(fn ($e) => $items->push(['at' => $e->created_at, 'icon' => 'book', 'text' => ($e->user?->name ?? '—').' سجّل في «'.($e->course?->tr('title') ?? '—').'»', 'url' => $e->course ? route('admin.courses.enrollments', $e->course) : null]));
        if ($ids === null) {
            CertificateRequest::with(['user:id,name'])->latest()->limit(6)->get()
                ->each(fn ($r) => $items->push(['at' => $r->created_at, 'icon' => 'award', 'text' => ($r->user?->name ?? '—').' طلب شهادة ('.$r->reference.')', 'url' => route('admin.certificate-requests.show', $r)]));
            ContactMessage::latest()->limit(4)->get()
                ->each(fn ($m) => $items->push(['at' => $m->created_at, 'icon' => 'mail', 'text' => 'رسالة تواصل من '.$m->name, 'url' => route('admin.messages.show', $m)]));
            Review::with(['user:id,name'])->latest()->limit(4)->get()
                ->each(fn ($r) => $items->push(['at' => $r->created_at, 'icon' => 'star', 'text' => ($r->user?->name ?? '—').' كتب مراجعة ('.$r->rating.'★)', 'url' => route('admin.reviews.index')]));
        }

        return $items->sortByDesc('at')->take(12)->values()->all();
    }

    /** Configuration checks shown to administrators. */
    private function health(): array
    {
        $uploadMb = round(\App\Support\Uploads::iniBytes((string) ini_get('upload_max_filesize')) / 1048576, 1);
        $free = @disk_free_space(storage_path());
        $freeGb = $free ? round($free / 1073741824, 1) : null;
        $demo = Course::where('is_demo', true)->count() + User::where('is_demo', true)->count();
        $wa = preg_replace('/\D+/', '', (string) Settings::get('whatsapp_number'));

        return [
            ['ok' => $wa !== '' && $wa !== '212600000000', 'label' => 'رقم واتساب الأكاديمية مضبوط', 'url' => route('admin.settings.edit', 'whatsapp')],
            ['ok' => ! in_array(config('mail.default'), ['log', 'array'], true), 'label' => 'إرسال البريد مفعّل (SMTP)', 'hint' => 'أضف إعدادات MAIL_* في ملف .env لتأكيد الحسابات واستعادة كلمات المرور.'],
            ['ok' => app()->configurationIsCached(), 'label' => 'الموقع مُسرَّع (ذاكرة مؤقتة)', 'hint' => 'اضغط «تسريع الموقع» في صفحة تحسين الموقع.', 'url' => route('admin.optimize.index'), 'optional' => true],
            ['ok' => (bool) Settings::get('google_verification'), 'label' => 'الموقع مربوط بـ Google Search Console', 'hint' => 'يساعد على الظهور في جوجل.', 'url' => route('admin.optimize.index'), 'optional' => true],
            ['ok' => ! config('app.debug'), 'label' => 'وضع التصحيح معطّل', 'hint' => 'اجعل APP_DEBUG=false في ملف .env.'],
            ['ok' => str_starts_with((string) config('app.url'), 'https://'), 'label' => 'الموقع يعمل بـ HTTPS', 'hint' => 'فعّل شهادة SSL من cPanel ثم اجعل APP_URL يبدأ بـ https://'],
            ['ok' => is_writable(storage_path('app/public')) && is_writable(storage_path('app/private')), 'label' => 'مجلدات التخزين قابلة للكتابة', 'hint' => 'اجعل صلاحيات المجلد storage 755.'],
            ['ok' => $demo === 0, 'label' => 'لا يوجد محتوى تجريبي', 'hint' => 'احذف الدورات والحسابات التجريبية قبل الإطلاق.', 'url' => route('admin.courses.index')],
            ['ok' => app(QuizGenerator::class)->enabled(), 'label' => 'مولّد الاختبارات بالذكاء الاصطناعي (اختياري)', 'url' => route('admin.settings.edit', 'ai'), 'optional' => true],
            ['ok' => true, 'info' => true, 'label' => 'حد رفع PHP: '.$uploadMb.' م.ب — الفيديو يُرفع على أجزاء تلقائيًا'],
            ['ok' => $freeGb === null || $freeGb > 2, 'info' => $freeGb === null, 'label' => 'المساحة المتاحة على الخادم: '.($freeGb === null ? 'غير معروفة' : $freeGb.' غ.ب')],
        ];
    }

    private function managedCourseIds(): array
    {
        $u = auth()->user();

        return Course::where('instructor_id', $u->id)->pluck('id')->merge($u->assignedCourses()->pluck('courses.id'))->unique()->values()->all();
    }
}
