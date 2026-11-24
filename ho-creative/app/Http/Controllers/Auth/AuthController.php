<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($cred + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('Identifiants incorrects.')]);
        }
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        if ($user->locale) {
            $request->session()->put('locale', $user->locale);
        }

        if ($user->is_staff) {
            return redirect()->intended($user->can('dashboard.view') ? route('admin.dashboard') : ($user->can('retail.sell') ? route('admin.pos') : route('admin.dashboard')));
        }

        return redirect()->intended(route('account.dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'required|string|max:40', 'company' => 'nullable|string|max:190',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);
        $user = DB::transaction(function () use ($data) {
            $customer = Customer::where('email', $data['email'])->whereDoesntHave('users')->first() ?? Customer::create([
                'type' => $data['company'] ? 'company' : 'individual', 'name' => $data['name'], 'company_name' => $data['company'] ?? null,
                'email' => $data['email'], 'phone' => $data['phone'], 'source' => 'web',
            ]);

            return User::create([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                'password' => $data['password'], 'locale' => app()->getLocale(), 'customer_id' => $customer->id,
            ]);
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('ok', __('Bienvenue ! Votre compte est créé.'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);
        }

        // Same answer whether or not the account exists (no account enumeration).
        return back()->with('ok', __('Si un compte existe pour cette adresse, un lien de réinitialisation vient d\'être envoyé.'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()]]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('ok', __('Mot de passe modifié. Vous pouvez vous connecter.'))
            : back()->withErrors(['email' => __('Lien invalide ou expiré.')]);
    }
}
