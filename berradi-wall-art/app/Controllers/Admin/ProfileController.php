<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Auth;

final class ProfileController extends Controller
{
    public function edit(): Response
    {
        return $this->view('admin/profile', ['user' => Auth::user()]);
    }

    public function update(): Response
    {
        $user = Auth::user();
        $id = (int) $user['id'];
        $data = $this->validate([
            'name' => 'required|max:150',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|phone',
            'locale' => 'required|locale',
        ]);
        Database::instance()->update('users', [
            'name' => $data['name'],
            'email' => mb_strtolower((string) $data['email']),
            'phone' => $data['phone'],
            'locale' => $data['locale'],
        ], ['id' => $id]);
        ActivityLogger::log('profile.updated', 'user', $id);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.profile');
    }

    public function password(): Response
    {
        $user = Auth::user();
        $data = $this->validate([
            'current_password' => 'required',
            'password' => 'required|password|confirmed|max:128|different:current_password',
        ]);
        $hash = (string) Database::instance()->value('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]);
        if (!password_verify((string) $data['current_password'], $hash)) {
            throw new ValidationException(['current_password' => __('auth.current_password_wrong')]);
        }
        Database::instance()->update('users', [
            'password_hash' => Auth::hashPassword((string) $data['password']),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $user['id']]);
        Auth::touchAdminSession();
        ActivityLogger::log('profile.password_changed', 'user', (int) $user['id']);
        $this->flash('success', __('auth.password_changed'));
        return $this->redirectRoute('admin.profile');
    }
}
