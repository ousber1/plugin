<?php

namespace App\Http\Controllers\Admin;

use App\Models\Center;
use App\Models\Course;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CenterController extends AdminController
{
    public function index()
    {
        $items = Center::orderBy('sort_order')->orderBy('id')
            ->withCount(['students', 'courses', 'certificateRequests'])->get();

        return view('admin.centers.index', compact('items'));
    }

    public function create()
    {
        return view('admin.centers.form', ['item' => new Center(['is_active' => true]), 'courses' => $this->courses()]);
    }

    public function store(Request $request)
    {
        $center = $this->save(new Center, $request);
        Audit::log('center.created', $center);

        return redirect()->route('admin.centers.index')->with('success', 'تمت إضافة المركز.');
    }

    public function edit(Center $center)
    {
        return view('admin.centers.form', ['item' => $center, 'courses' => $this->courses()]);
    }

    public function update(Request $request, Center $center)
    {
        $this->save($center, $request);
        Audit::log('center.updated', $center);

        return redirect()->route('admin.centers.index')->with('success', 'تم حفظ المركز.');
    }

    public function destroy(Center $center)
    {
        if ($center->image_path) {
            Storage::disk('public')->delete($center->image_path);
        }
        Audit::log('center.deleted', $center, ['name' => $center->tr('name')]);
        $center->delete();

        return back()->with('success', 'تم حذف المركز. بقي الطلاب وطلبات الشهادات المرتبطة به دون مركز.');
    }

    private function courses()
    {
        return Course::orderByDesc('id')->get(['id', 'title', 'status']);
    }

    private function save(Center $item, Request $request): Center
    {
        $data = $request->validate([
            ...$this->trRules('name', true, 120), ...$this->trRules('city', true, 80), ...$this->trRules('address', false, 255),
            ...$this->trRules('hours', false, 160), ...$this->trRules('description', false, 5000),
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'map_url' => ['nullable', 'url', 'max:500', 'regex:/^https:\/\//'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'course_ids' => ['nullable', 'array'], 'course_ids.*' => ['integer', 'exists:courses,id'],
        ], ['whatsapp.regex' => 'أدخل رقم واتساب بالصيغة الدولية، مثل 212600000000.']);

        $item->fill([
            'name' => $this->trInput($request, 'name'), 'city' => $this->trInput($request, 'city'),
            'address' => $this->trInput($request, 'address'), 'hours' => $this->trInput($request, 'hours'),
            'description' => $this->trInput($request, 'description', 'rich'),
            'phone' => $data['phone'] ?? null, 'whatsapp' => preg_replace('/\D+/', '', (string) ($data['whatsapp'] ?? '')) ?: null,
            'email' => $data['email'] ?? null, 'map_url' => $data['map_url'] ?? null,
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_active' => $request->boolean('is_active'),
        ]);
        if (! $item->exists || ! $item->slug) {
            $item->slug = $this->uniqueSlug(Center::class, ($request->input('city.fr') ?: $request->input('city.en') ?: '').' '.($request->input('name.fr') ?: $request->input('name.en') ?: $request->input('name.ar')), $item->id);
        }
        if (($request->boolean('remove_image') || $request->hasFile('image')) && $item->image_path) {
            Storage::disk('public')->delete($item->image_path);
            $item->image_path = null;
        }
        if ($request->hasFile('image')) {
            $item->image_path = \App\Support\ImageOptimizer::store($request->file('image'), 'centers');
        }
        $item->save();
        $item->courses()->sync($data['course_ids'] ?? []);
        Center::flushCache();

        return $item;
    }
}
