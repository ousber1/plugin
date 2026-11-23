<?php

namespace App\Http\Controllers\Admin;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends AdminController
{
    private const PROTECTED = ['about', 'privacy'];

    public function index()
    {
        return view('admin.pages.index', ['pages' => Page::orderBy('slug')->get()]);
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['status' => 'published'])]);
    }

    public function store(Request $request)
    {
        $page = new Page;
        $this->save($page, $request);

        return redirect()->route('admin.pages.index')->with('success', 'تم إنشاء الصفحة.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $this->save($page, $request);

        return back()->with('success', 'تم حفظ الصفحة.');
    }

    public function destroy(Page $page)
    {
        if (in_array($page->slug, self::PROTECTED, true)) {
            return back()->with('error', 'هذه الصفحة أساسية في القائمة ولا يمكن حذفها. يمكنك تعديل محتواها.');
        }
        $page->delete();

        return back()->with('success', 'تم حذف الصفحة.');
    }

    private function save(Page $page, Request $request): void
    {
        $data = $request->validate([
            ...$this->trRules('title', true, 190),
            ...$this->trRules('body', false, 200000),
            ...$this->trRules('seo_title', false, 190),
            ...$this->trRules('seo_description', false, 320),
            'slug' => [$page->exists ? 'nullable' : 'required', 'alpha_dash', 'max:80', Rule::unique('pages')->ignore($page->id)],
            'status' => ['required', 'in:draft,published'],
            'show_in_footer' => ['nullable', 'boolean'],
        ]);
        $page->fill([
            'title' => $this->trInput($request, 'title'), 'body' => $this->trInput($request, 'body', 'rich'),
            'seo_title' => $this->trInput($request, 'seo_title'), 'seo_description' => $this->trInput($request, 'seo_description'),
            'status' => in_array($page->slug, self::PROTECTED, true) ? 'published' : $data['status'],
            'show_in_footer' => $request->boolean('show_in_footer'),
        ]);
        if (! in_array($page->slug, self::PROTECTED, true) && ! empty($data['slug'])) {
            $page->slug = $data['slug'];
        }
        $page->save();
    }
}
