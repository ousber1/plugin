<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Auth;
use App\Services\PasswordResetService;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return $this->view('admin/auth/login');
    }

    public function login(): Response
    {
        $data = $this->validate(['email' => 'required|email', 'password' => 'required|max:200']);
        $result = Auth::attemptAdmin((string) $data['email'], (string) $data['password'], $this->request);
        if (!$result['ok']) {
            $message = match ($result['error'] ?? 'invalid') {
                'throttled' => __('auth.throttled', ['minutes' => (int) ceil(($result['retry_after'] ?? 60) / 60)]),
                'inactive' => __('auth.inactive'),
                default => __('auth.failed'),
            };
            throw new ValidationException(['email' => $message], ['email' => $data['email']]);
        }
        $intended = Session::pull('url.intended_admin');
        $adminPrefix = '/' . trim((string) config('app.admin_path', 'admin'), '/');
        if (is_string($intended) && str_starts_with($intended, $adminPrefix)) {
            return $this->redirect(url($intended));
        }
        return $this->redirectRoute('admin.dashboard');
    }

    public function logout(): Response
    {
        Auth::logoutAdmin();
        $this->flash('success', __('auth.logged_out'));
        return $this->redirectRoute('admin.login');
    }

    public function showForgot(): Response
    {
        return $this->view('admin/auth/forgot');
    }

    public function sendReset(): Response
    {
        $data = $this->validate(['email' => 'required|email']);
        PasswordResetService::request('user', (string) $data['email'], $this->request);
        $this->flash('success', __('auth.reset_sent'));
        return $this->redirectRoute('admin.login');
    }

    public function showReset(string $token): Response
    {
        if (PasswordResetService::find('user', $token) === null) {
            $this->flash('danger', __('auth.reset_invalid'));
            return $this->redirectRoute('admin.password.request');
        }
        return $this->view('admin/auth/reset', ['token' => $token]);
    }

    public function reset(): Response
    {
        $data = $this->validate([
            'token' => 'required|regex:/^[a-f0-9]{64}$/',
            'password' => 'required|password|confirmed|max:128',
        ]);
        if (!PasswordResetService::reset('user', (string) $data['token'], (string) $data['password'])) {
            $this->flash('danger', __('auth.reset_invalid'));
            return $this->redirectRoute('admin.password.request');
        }
        $this->flash('success', __('auth.reset_done'));
        return $this->redirectRoute('admin.login');
    }
}
