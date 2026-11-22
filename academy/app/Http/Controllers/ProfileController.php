<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('dashboard.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', Rule::in(Locales::codes())],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $emailChanged = $data['email'] !== $user->email;
        $user->fill(collect($data)->only(['name', 'email', 'phone', 'locale'])->all());
        if ($request->hasFile('avatar')) {
            $old = $user->avatar_path;
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
            if ($old) {
                \Storage::disk('public')->delete($old);
            }
        }
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
        if (! empty($data['locale'])) {
            $request->session()->put('locale', $data['locale']);
        }

        return back()->with('success', __('تم حفظ بيانات الملف الشخصي.'));
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $request->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('success', __('تم تغيير كلمة المرور.'));
    }
}
