<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    use RemembersIntendedUrl;

    public function create(Request $request)
    {
        $this->rememberIntended($request);

        return view('auth.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
            'center_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('centers', 'id')->where('is_active', true)],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);
        $user->role = 'student';
        $user->center_id = $data['center_id'] ?? null;
        $user->save();

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('success', __('مرحبًا بك! أرسلنا رابط تأكيد إلى بريدك الإلكتروني.'));
    }
}
