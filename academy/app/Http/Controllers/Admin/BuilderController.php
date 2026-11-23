<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Page;
use App\Support\Audit;
use App\Support\Builder;
use App\Support\Media;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Visual page builder for the homepage and static pages. */
class BuilderController extends AdminController
{
    public function edit(string $target)
    {
        [$label, $blocks, $live, $viewUrl] = $this->load($target);

        return view('admin.builder.edit', [
            'target' => $target, 'label' => $label, 'blocks' => $blocks, 'live' => $live, 'viewUrl' => $viewUrl,
            'defs' => Builder::blocks(), 'styleFields' => Builder::styleFields(), 'icons' => Builder::ICONS,
            'categories' => Category::ofType('course')->get()->map(fn ($c) => ['v' => $c->slug, 'label' => $c->tr('name')])->values(),
        ]);
    }

    public function save(Request $request, string $target)
    {
        $request->validate(['blocks' => ['present', 'array', 'max:80'], 'publish' => ['nullable', 'boolean']]);
        $blocks = Builder::sanitize($request->input('blocks'));
        $publish = $request->boolean('publish', true);

        if ($target === 'home') {
            Settings::set(['home_blocks' => $blocks, 'home_builder_enabled' => $publish ? true : (bool) Settings::get('home_builder_enabled')]);
        } else {
            $page = $this->page($target);
            $page->blocks = $blocks;
            if ($publish) {
                $page->use_builder = true;
            }
            $page->save();
        }
        Audit::log('builder.saved', $target === 'home' ? null : $this->page($target), ['target' => $target, 'blocks' => count($blocks)]);

        return response()->json(['ok' => true, 'blocks' => $blocks, 'message' => 'تم الحفظ والنشر.']);
    }

    /** Renders unsaved blocks inside the editor's preview frame. */
    public function preview(Request $request, string $target)
    {
        $this->load($target);
        $blocks = Builder::sanitize(json_decode((string) $request->input('blocks', '[]'), true));
        $locale = $request->input('lang');
        if (in_array($locale, ['ar', 'fr', 'en'], true)) {
            app()->setLocale($locale);
        }

        return response()->view('builder.page', ['blocks' => $blocks, 'editing' => true, 'pageTitle' => 'معاينة'])
            ->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
    }

    public function upload(Request $request)
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:6144']], [
            'image.image' => 'الملف ليس صورة.', 'image.mimes' => 'الصيغ المقبولة: JPG، PNG، WebP، GIF.', 'image.max' => 'حجم الصورة أكبر من 6 م.ب.',
        ]);
        $path = $request->file('image')->store('builder', 'public');

        return response()->json(['path' => $path, 'url' => Media::url($path)]);
    }

    /** Switch back to the default (non-builder) design. */
    public function disable(string $target)
    {
        if ($target === 'home') {
            Settings::set(['home_builder_enabled' => false]);
        } else {
            $this->page($target)->forceFill(['use_builder' => false])->save();
        }

        return back()->with('success', 'عادت الصفحة إلى التصميم الافتراضي. محتوى المحرّر محفوظ ويمكنك العودة إليه.');
    }

    private function load(string $target): array
    {
        if ($target === 'home') {
            $blocks = Settings::get('home_blocks') ?: Builder::defaultHome();

            return ['الصفحة الرئيسية', Builder::sanitize($blocks), (bool) Settings::get('home_builder_enabled'), route('home')];
        }
        $page = $this->page($target);

        return [$page->tr('title'), Builder::sanitize($page->blocks ?: Builder::defaultPage($page->tr('title'))), (bool) $page->use_builder, route('pages.show', $page)];
    }

    private function page(string $target): Page
    {
        abort_unless(ctype_digit($target), 404);

        return Page::findOrFail((int) $target);
    }
}
