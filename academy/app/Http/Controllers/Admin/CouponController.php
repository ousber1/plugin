<?php

namespace App\Http\Controllers\Admin;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends AdminController
{
    public function index()
    {
        return view('admin.coupons.index', ['items' => Coupon::latest()->get()]);
    }

    public function create()
    {
        return view('admin.coupons.form', ['item' => new Coupon(['type' => 'percent', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $this->save(new Coupon, $request);

        return redirect()->route('admin.coupons.index')->with('success', 'تمت إضافة رمز الخصم.');
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', ['item' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->save($coupon, $request);

        return redirect()->route('admin.coupons.index')->with('success', 'تم الحفظ.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return back()->with('success', 'تم الحذف.');
    }

    private function save(Coupon $item, Request $request): void
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($item->id)],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:1000000'],
            'max_uses' => ['nullable', 'integer', 'min:1'], 'expires_at' => ['nullable', 'date'],
        ], ['code.regex' => 'استعمل أحرفًا لاتينية وأرقامًا فقط، مثل RAMADAN25.']);
        $item->fill($data + ['is_active' => $request->boolean('is_active')])->save();
    }
}
