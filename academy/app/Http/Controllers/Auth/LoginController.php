<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use RemembersIntendedUrl;

    public function create(Request $request)
    {
        $this->rememberIntended($request);

        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('البريد الإلكتروني أو كلمة المرور غير صحيحة.')]);
        }
        $request->session()->regenerate();

        $default = $request->user()->isStaff() ? route('admin.dashboard') : route('dashboard');

        return redirect()->intended($default);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
