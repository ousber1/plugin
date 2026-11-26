<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\PasswordResetService;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return $this->view('customer/auth/login');
    }

    public function login(): Response
    {
        $data = $this->validate(['email' => 'required|email', 'password' => 'required|max:200']);
        $result = Auth::attemptCustomer((string) $data['email'], (string) $data['password'], $this->request);
        if (!$result['ok']) {
            $message = match ($result['error'] ?? 'invalid') {
                'throttled' => __('auth.throttled', ['minutes' => (int) ceil(($result['retry_after'] ?? 60) / 60)]),
                'inactive' => __('auth.inactive'),
                default => __('auth.failed'),
            };
            throw new ValidationException(['email' => $message], ['email' => $data['email']]);
        }
        return $this->redirectIntended();
    }

    private function redirectIntended(): Response
    {
        $intended = Session::pull('url.intended_customer');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            return $this->redirect(url($intended));
        }
        return $this->redirectRoute('customer.account');
    }

    public function showRegister(): Response
    {
        return $this->view('customer/auth/register');
    }

    public function register(): Response
    {
        $data = $this->validate([
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'password' => 'required|password|confirmed|max:128',
            'accept_terms' => 'required|in:1,on',
        ]);
        $email = mb_strtolower((string) $data['email']);
        $db = Database::instance();
        $existing = $db->fetch('SELECT id, is_guest FROM customers WHERE email = ?', [$email]);
        if ($existing !== null && (int) $existing['is_guest'] === 0) {
            throw new ValidationException(['email' => __('auth.email_taken')], $this->request->all());
        }
        $values = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'],
            'password_hash' => Auth::hashPassword((string) $data['password']),
            'is_guest' => 0,
            'locale' => Translator::locale(),
            'marketing_opt_in' => $this->request->bool('marketing_opt_in') ? 1 : 0,
        ];
        // a previous guest checkout with the same e-mail is upgraded to a full account (keeps order history)
        if ($existing !== null) {
            $db->update('customers', $values, ['id' => (int) $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = $db->insert('customers', $values);
        }
        Auth::loginCustomer($id);
        ActivityLogger::log('customer.registered', 'customer', $id);
        $this->flash('success', __('auth.welcome', ['name' => (string) $data['first_name']]));
        return $this->redirectIntended();
    }

    public function logout(): Response
    {
        Auth::logoutCustomer();
        $this->flash('success', __('auth.logged_out'));
        return $this->redirectRoute('home');
    }

    public function showForgot(): Response
    {
        return $this->view('customer/auth/forgot');
    }

    public function sendReset(): Response
    {
        $data = $this->validate(['email' => 'required|email']);
        PasswordResetService::request('customer', (string) $data['email'], $this->request);
        $this->flash('success', __('auth.reset_sent'));
        return $this->redirectRoute('customer.login');
    }

    public function showReset(string $token): Response
    {
        if (PasswordResetService::find('customer', $token) === null) {
            $this->flash('danger', __('auth.reset_invalid'));
            return $this->redirectRoute('customer.password.request');
        }
        return $this->view('customer/auth/reset', ['token' => $token]);
    }

    public function reset(): Response
    {
        $data = $this->validate([
            'token' => 'required|regex:/^[a-f0-9]{64}$/',
            'password' => 'required|password|confirmed|max:128',
        ]);
        if (!PasswordResetService::reset('customer', (string) $data['token'], (string) $data['password'])) {
            $this->flash('danger', __('auth.reset_invalid'));
            return $this->redirectRoute('customer.password.request');
        }
        $this->flash('success', __('auth.reset_done'));
        return $this->redirectRoute('customer.login');
    }
}
