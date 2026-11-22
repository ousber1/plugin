<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Course;
use App\Models\Resource;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResourceController extends AdminController
{
    public function index(Request $request)
    {
        $items = Resource::with(['category', 'course:id,title,slug'])
            ->searchTranslated(['title'], $request->input('q'))->latest()->paginate(25)->withQueryString();

        return view('admin.resources.index', compact('items'));
    }

    public function create()
    {
        return view('admin.resources.form', ['item' => new Resource(['access' => 'public', 'status' => 'draft']), ...$this->formData()]);
    }

    public function store(Request $request, MediaService $media)
    {
        $item = new Resource;
        $this->save($item, $request, $media);

        return redirect()->route('admin.resources.index')->with('success', 'تمت إضافة المورد.');
    }

    public function edit(Resource $resource)
    {
        return view('admin.resources.form', ['item' => $resource, ...$this->formData()]);
    }

    public function update(Request $request, Resource $resource, MediaService $media)
    {
        $this->save($resource, $request, $media);

        return redirect()->route('admin.resources.index')->with('success', 'تم حفظ المورد.');
    }

    public function destroy(Resource $resource, MediaService $media)
    {
        $media->delete($resource->disk, $resource->path);
        $resource->delete();

        return back()->with('success', 'تم حذف المورد.');
    }

    private function formData(): array
    {
        return ['categories' => Category::ofType('resource')->get(), 'courses' => Course::orderBy('slug')->get(['id', 'title', 'slug'])];
    }

    private function save(Resource $item, Request $request, MediaService $media): void
    {
        $data = $request->validate([
            ...$this->trRules('title', true, 190),
            ...$this->trRules('description', false, 1000),
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('type', 'resource')],
            'access' => ['required', 'in:public,enrolled'],
            'course_id' => ['nullable', 'required_if:access,enrolled', 'exists:courses,id'],
            'status' => ['required', 'in:draft,published'],
            'file' => [$item->exists ? 'nullable' : 'required', 'file', 'mimes:'.config('academy.attachments.mimes'), 'max:'.(config('academy.attachments.max_mb') * 1024)],
        ], ['course_id.required_if' => 'اختر الدورة التي يقتصر عليها هذا المورد.']);

        $item->fill([
            'title' => $this->trInput($request, 'title'), 'description' => $this->trInput($request, 'description'),
            'category_id' => $data['category_id'] ?? null, 'access' => $data['access'],
            'course_id' => $data['access'] === 'enrolled' ? $data['course_id'] : ($data['course_id'] ?? null),
            'status' => $data['status'],
        ]);
        $t = $data['title'];
        $item->slug = $item->slug ?: $this->uniqueSlug(Resource::class, ($t['en'] ?? '') ?: (($t['fr'] ?? '') ?: $t['ar']));
        if ($request->hasFile('file')) {
            $stored = $media->storeUpload($request->file('file'), 'library');
            $old = [$item->disk, $item->path];
            $item->forceFill($stored);
            $item->save();
            $media->delete($old[0], $old[1]);
        } else {
            $item->disk ??= $media->disk();
            $item->save();
        }
    }
}
