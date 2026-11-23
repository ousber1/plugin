<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => mb_strtolower($request->input('email'))]);

        // Same answer whether or not the address exists (no account enumeration).
        return back()->with('status', __('إذا كان البريد مسجلًا لدينا، فستصلك رسالة تحتوي على رابط إعادة التعيين.'));
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __('تم تعيين كلمة المرور الجديدة. يمكنك تسجيل الدخول الآن.'))
            : back()->withInput($request->only('email'))->withErrors(['email' => __('رابط إعادة التعيين غير صالح أو منتهي الصلاحية.')]);
    }
}
