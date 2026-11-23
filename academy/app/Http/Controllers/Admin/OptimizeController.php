<?php

namespace App\Http\Controllers\Admin;

use App\Models\Center;
use App\Models\Course;
use App\Models\Page;
use App\Models\Post;
use App\Support\Audit;
use App\Support\ImageOptimizer;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/** «تحسين الموقع»: speed, images, SEO audit, cleanup and caches in one place. */
class OptimizeController extends AdminController
{
    public function index()
    {
        $images = $this->imageStats();
        $audit = $this->seoAudit();
        $passed = collect($audit)->where('ok', true)->count();

        return view('admin.optimize.index', [
            's' => Settings::all(),
            'images' => $images,
            'audit' => $audit,
            'score' => count($audit) ? (int) round($passed * 100 / count($audit)) : 100,
            'speed' => $this->speedChecks(),
            'storage' => $this->storageStats(),
            'gd' => ImageOptimizer::available(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'image_max_width' => ['required', 'integer', 'between:800,4000'],
            'image_quality' => ['required', 'integer', 'between:50,95'],
            'google_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]*$/'],
            'bing_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]*$/'],
            'robots_extra' => ['nullable', 'string', 'max:2000'],
            'og_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_og_image' => ['nullable', 'boolean'],
        ], ['regex' => 'الصق قيمة content فقط (أحرف وأرقام)، وليس وسم meta كاملًا.']);
        $robots = collect(preg_split('/\R/', (string) ($data['robots_extra'] ?? '')))
            ->map(fn ($l) => trim($l))->filter(fn ($l) => preg_match('/^(Disallow|Allow):\s*\/\S*$/i', $l))->implode("\n");

        $values = [
            'image_optimize' => $request->boolean('image_optimize'),
            'image_max_width' => (int) $data['image_max_width'],
            'image_quality' => (int) $data['image_quality'],
            'lazy_images' => $request->boolean('lazy_images'),
            'html_minify' => $request->boolean('html_minify'),
            'seo_title_suffix' => $request->boolean('seo_title_suffix'),
            'org_schema' => $request->boolean('org_schema'),
            'google_verification' => (string) ($data['google_verification'] ?? ''),
            'bing_verification' => (string) ($data['bing_verification'] ?? ''),
            'robots_extra' => $robots,
        ];
        if (($request->boolean('remove_og_image') || $request->hasFile('og_image')) && Settings::get('og_image_path')) {
            Storage::disk('public')->delete(Settings::get('og_image_path'));
            $values['og_image_path'] = null;
        }
        if ($request->hasFile('og_image')) {
            $values['og_image_path'] = $request->file('og_image')->store('branding', 'public');
        }
        Settings::set($values);
        Audit::log('settings.updated', null, ['group' => 'optimize', 'keys' => array_keys($values)]);

        return back()->with('success', 'تم حفظ إعدادات التحسين.');
    }

    public function run(string $action)
    {
        $message = match ($action) {
            'cache' => $this->cacheAll(),
            'clear' => $this->clearAll(),
            'images' => $this->optimizeImages(),
            'cleanup' => $this->cleanup(),
            default => abort(404),
        };
        Audit::log('system.optimized', null, ['action' => $action]);

        return back()->with('success', $message);
    }

    private function cacheAll(): string
    {
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Artisan::call('event:cache');
        } catch (\Throwable $e) {
            report($e);
            Artisan::call('optimize:clear');

            return 'تعذّر تفعيل التخزين المؤقت الكامل على هذا الخادم، فأُلغي بأمان: '.$e->getMessage();
        }

        return 'تم تسريع الموقع: حُفظت الإعدادات والمسارات والقوالب في ذاكرة مؤقتة. أعد الضغط بعد كل تحديث للملفات.';
    }

    private function clearAll(): string
    {
        Artisan::call('optimize:clear');
        Settings::flush();

        return 'تم مسح كل الذاكرة المؤقتة. ستظهر آخر التعديلات فورًا.';
    }

    private function optimizeImages(): string
    {
        if (! ImageOptimizer::available()) {
            return 'إضافة GD غير مفعّلة في PHP على الخادم؛ فعّلها من cPanel › Select PHP Version.';
        }
        $started = microtime(true);
        $saved = 0;
        $done = 0;
        $state = Settings::get('images_optimized', []);
        $state = is_array($state) ? $state : [];
        foreach ($this->imageFiles() as $path => $size) {
            if (isset($state[$path]) && $state[$path] === $size) {
                continue;
            }
            $gain = ImageOptimizer::optimizeExisting($path);
            $saved += $gain;
            $state[$path] = Storage::disk('public')->size($path);
            $done++;
            if (microtime(true) - $started > 20) {
                break; // keep each click short on shared hosting
            }
        }
        Settings::set(['images_optimized' => $state]);
        $left = $this->imageStats()['pending'];

        return sprintf('تمت معالجة %d صورة ووُفّر %s.', $done, $this->human($saved)).($left ? ' بقيت '.$left.' صورة، اضغط مرة أخرى لإكمالها.' : ' كل الصور محسّنة الآن.');
    }

    private function cleanup(): string
    {
        $freed = 0;
        // Abandoned chunked uploads
        Artisan::call('academy:prune-uploads');
        // Old log files and oversized current log
        foreach (File::glob(storage_path('logs/*.log')) as $log) {
            if (filemtime($log) < now()->subDays(14)->timestamp || filesize($log) > 5 * 1024 * 1024) {
                $freed += filesize($log);
                @unlink($log);
            }
        }
        // Compiled views are rebuilt on demand
        foreach (File::glob(storage_path('framework/views/*.php')) as $v) {
            $freed += filesize($v);
            @unlink($v);
        }
        $rows = 0;
        try {
            if (config('session.driver') === 'database') {
                $rows += DB::table(config('session.table', 'sessions'))->where('last_activity', '<', now()->subMinutes(config('session.lifetime'))->timestamp)->delete();
            }
            if (config('cache.default') === 'database') {
                $rows += DB::table(config('cache.stores.database.table', 'cache'))->where('expiration', '<', now()->timestamp)->delete();
            }
            $rows += DB::table('password_reset_tokens')->where('created_at', '<', now()->subDay())->delete();
            $rows += DB::table('audit_logs')->where('created_at', '<', now()->subYear())->delete();
        } catch (\Throwable $e) {
            report($e);
        }

        return sprintf('تم التنظيف: حُذف %s من الملفات المؤقتة و%d سجلًا منتهيًا من قاعدة البيانات.', $this->human($freed), $rows);
    }

    /** @return array<string,int> path => bytes */
    private function imageFiles(): array
    {
        $disk = Storage::disk('public');
        $out = [];
        foreach ($disk->allFiles() as $path) {
            if (preg_match('/\.(jpe?g|png|webp)$/i', $path) && ! str_starts_with(basename($path), '.')) {
                $out[$path] = $disk->size($path);
            }
        }

        return $out;
    }

    private function imageStats(): array
    {
        $files = $this->imageFiles();
        $state = Settings::get('images_optimized', []);
        $state = is_array($state) ? $state : [];
        $heavy = collect($files)->filter(fn ($s) => $s > 400 * 1024);
        $pending = collect($files)->filter(fn ($s, $p) => ($state[$p] ?? null) !== $s && $s >= 60 * 1024)->count();

        return ['count' => count($files), 'bytes' => array_sum($files), 'heavy' => $heavy->count(), 'pending' => $pending];
    }

    private function storageStats(): array
    {
        $size = function (string $dir) {
            $total = 0;
            if (is_dir($dir)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                    $total += $f->getSize();
                }
            }

            return $total;
        };

        return [
            'videos' => $size(storage_path('app/private/videos')),
            'public' => $size(storage_path('app/public')),
            'logs' => $size(storage_path('logs')),
            'cache' => $size(storage_path('framework')),
        ];
    }

    private function speedChecks(): array
    {
        return [
            ['label' => 'الإعدادات والمسارات في الذاكرة المؤقتة', 'ok' => app()->configurationIsCached() && app()->routesAreCached(), 'hint' => 'اضغط «تسريع الموقع» أدناه.'],
            ['label' => 'وضع الإنتاج (APP_DEBUG=false)', 'ok' => ! config('app.debug'), 'hint' => 'غيّر APP_DEBUG إلى false في ملف .env.'],
            ['label' => 'إصدار PHP 8.3 أو أحدث', 'ok' => version_compare(PHP_VERSION, '8.3', '>='), 'hint' => 'الحالي '.PHP_VERSION.'. غيّره من cPanel › Select PHP Version.'],
            ['label' => 'OPcache مفعّل (تسريع PHP)', 'ok' => function_exists('opcache_get_status') && (bool) @opcache_get_status(false), 'hint' => 'فعّل opcache من cPanel › Select PHP Version › Extensions.', 'optional' => true],
            ['label' => 'ضغط الصور تلقائيًا عند الرفع', 'ok' => (bool) Settings::get('image_optimize') && ImageOptimizer::available(), 'hint' => ImageOptimizer::available() ? 'فعّله في الإعدادات أدناه.' : 'إضافة GD غير مفعّلة في PHP.'],
            ['label' => 'تحميل الصور عند الحاجة (Lazy loading)', 'ok' => (bool) Settings::get('lazy_images'), 'hint' => 'فعّله في الإعدادات أدناه.'],
            ['label' => 'الاتصال الآمن HTTPS', 'ok' => str_starts_with(config('app.url'), 'https://'), 'hint' => 'فعّل SSL من cPanel واجعل APP_URL يبدأ بـ https://'],
        ];
    }

    private function seoAudit(): array
    {
        $courses = Course::published()->get();
        $posts = Post::published()->get();
        $checks = [];
        $add = function (string $label, int $bad, ?string $url, string $hint) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => $bad === 0, 'bad' => $bad, 'url' => $url, 'hint' => $hint];
        };
        $add('دورات بدون صورة غلاف', $courses->whereNull('thumbnail_path')->count(), route('admin.courses.index'), 'الصورة تظهر عند المشاركة في فيسبوك وواتساب ونتائج البحث.');
        $add('دورات بوصف قصير (أقل من 120 حرفًا)', $courses->filter(fn ($c) => mb_strlen(strip_tags($c->tr('description'))) < 120)->count(), route('admin.courses.index'), 'الوصف الجيد يساعد جوجل على فهم الدورة.');
        $add('دورات بدون وصف لمحركات البحث', $courses->filter(fn ($c) => trim($c->tr('seo_description')) === '' && trim($c->tr('subtitle')) === '')->count(), route('admin.courses.index'), 'أضف عنوانًا فرعيًا أو وصف SEO.');
        $add('دورات بدون ترجمة فرنسية للعنوان', $courses->filter(fn ($c) => ! $c->hasTranslation('title', 'fr'))->count(), route('admin.courses.index'), 'الترجمة تجلب زوارًا يبحثون بالفرنسية.');
        $add('مقالات بدون صورة', $posts->whereNull('cover_path')->count(), route('admin.posts.index'), 'المقالات المصوّرة تُشارك أكثر.');
        $add('مقالات بدون مقتطف', $posts->filter(fn ($p) => trim($p->tr('excerpt')) === '')->count(), route('admin.posts.index'), 'المقتطف يظهر في نتائج البحث.');
        $add('صفحات بدون محتوى كافٍ', Page::where('status', 'published')->get()->filter(fn ($p) => ! $p->use_builder && mb_strlen(strip_tags($p->tr('body'))) < 200)->count(), route('admin.pages.index'), 'صفحات «من نحن» والخصوصية تزيد ثقة الزوار وجوجل.');
        if (Settings::feature('centers')) {
            $add('مراكز بدون صورة أو عنوان', Center::where('is_active', true)->get()->filter(fn ($c) => ! $c->image_path || trim($c->tr('address')) === '')->count(), route('admin.centers.index'), 'تظهر المراكز الكاملة بشكل أفضل في بحث جوجل المحلي.');
        }
        $desc = mb_strlen(Settings::tr('seo_description'));
        $add('الوصف العام للموقع بين 70 و160 حرفًا', ($desc >= 70 && $desc <= 170) ? 0 : 1, route('admin.settings.edit', 'general'), 'الحالي: '.$desc.' حرفًا.');
        $add('صورة المشاركة الافتراضية (Open Graph)', Settings::get('og_image_path') ? 0 : 1, null, 'ارفع صورة 1200×630 في الإعدادات أدناه.');
        $add('شعار الأكاديمية مرفوع', Settings::get('logo_path') ? 0 : 1, route('admin.settings.edit', 'general'), 'يُستعمل الشعار الافتراضي حاليًا.');
        $add('التحقق من ملكية الموقع في Google Search Console', Settings::get('google_verification') ? 0 : 1, null, 'الصق رمز التحقق في الإعدادات أدناه، ثم أرسل خريطة الموقع.');
        $images = $this->imageStats();
        $add('صور أكبر من 400 ك.ب', $images['heavy'], null, 'استعمل زر «ضغط الصور» أدناه.');

        return $checks;
    }

    private function human(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' م.ب' : round($bytes / 1024).' ك.ب';
    }
}
