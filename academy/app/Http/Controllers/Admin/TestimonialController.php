<?php

namespace App\Http\Controllers\Admin;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestimonialController extends AdminController
{
    public function index()
    {
        return view('admin.testimonials.index', ['items' => Testimonial::orderBy('sort_order')->latest()->paginate(25)]);
    }

    public function create()
    {
        return view('admin.testimonials.form', ['item' => new Testimonial(['is_approved' => true])]);
    }

    public function store(Request $request)
    {
        $item = new Testimonial;
        $this->save($item, $request);

        return redirect()->route('admin.testimonials.index')->with('success', 'تمت الإضافة.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.form', ['item' => $testimonial]);
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $this->save($testimonial, $request);

        return redirect()->route('admin.testimonials.index')->with('success', 'تم الحفظ.');
    }

    public function destroy(Testimonial $testimonial)
    {
        if ($testimonial->photo_path) {
            Storage::disk('public')->delete($testimonial->photo_path);
        }
        $testimonial->delete();

        return back()->with('success', 'تم الحذف.');
    }

    private function save(Testimonial $item, Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            ...$this->trRules('body', true, 1500),
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_approved' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $item->fill([
            'name' => $data['name'], 'role' => $data['role'] ?? null, 'body' => $this->trInput($request, 'body'),
            'rating' => $data['rating'] ?? null, 'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_approved' => $request->boolean('is_approved'),
        ]);
        if ($request->hasFile('photo')) {
            if ($item->photo_path) {
                Storage::disk('public')->delete($item->photo_path);
            }
            $item->photo_path = $request->file('photo')->store('testimonials', 'public');
        }
        $item->save();
    }
}
