<?php

namespace App\Http\Controllers\Admin;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PartnerController extends AdminController
{
    public function index()
    {
        return view('admin.partners.index', ['items' => Partner::orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.partners.form', ['item' => new Partner(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $this->save(new Partner, $request);

        return redirect()->route('admin.partners.index')->with('success', 'تمت إضافة الشريك.');
    }

    public function edit(Partner $partner)
    {
        return view('admin.partners.form', ['item' => $partner]);
    }

    public function update(Request $request, Partner $partner)
    {
        $this->save($partner, $request);

        return redirect()->route('admin.partners.index')->with('success', 'تم الحفظ.');
    }

    public function destroy(Partner $partner)
    {
        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }
        $partner->delete();

        return back()->with('success', 'تم الحذف.');
    }

    private function save(Partner $item, Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => [$item->exists ? 'nullable' : 'required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $item->fill(['name' => $data['name'], 'url' => $data['url'] ?? null, 'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_active' => $request->boolean('is_active')]);
        if ($request->hasFile('logo')) {
            if ($item->logo_path) {
                Storage::disk('public')->delete($item->logo_path);
            }
            $item->logo_path = $request->file('logo')->store('partners', 'public');
        }
        $item->save();
    }
}
