<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Validator;

/**
 * Profil de l'utilisateur connecté : coordonnées, thème, mot de passe.
 */
final class ProfileController extends Controller
{
    public function show(): void
    {
        $user = $this->db->fetch(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
            [Auth::id()]
        );
        $logins = $this->db->fetchAll(
            "SELECT created_at, ip_address, user_agent, success FROM login_attempts
              WHERE identifier IN (?, ?) ORDER BY id DESC LIMIT 8",
            [mb_strtolower((string) $user['username']), mb_strtolower((string) $user['email'])]
        );
        $this->view('profile/index', ['title' => 'Mon profil', 'user' => $user, 'logins' => $logins]);
    }

    public function update(): void
    {
        $id = (int) Auth::id();
        $before = $this->db->fetch('SELECT full_name, email, phone, theme FROM users WHERE id = ?', [$id]);
        $input = [
            'full_name' => Request::string('full_name'),
            'email'     => mb_strtolower(Request::string('email')),
            'phone'     => Request::string('phone'),
            'theme'     => Request::string('theme'),
        ];
        $v = Validator::make($input, [
            'full_name' => 'required|minlen:2|maxlen:150',
            'email'     => 'required|email|maxlen:190|unique:users,email,' . $id,
            'phone'     => 'nullable|phone',
            'theme'     => 'required|in:auto,light,dark',
        ], ['full_name' => 'Nom complet', 'email' => 'E-mail', 'phone' => 'Téléphone', 'theme' => 'Thème']);
        if ($v->fails()) {
            Session::flash('danger', (string) $v->first());
            Session::flashInput($_POST);
            $this->redirect('profile');
        }
        $input['phone'] = $input['phone'] !== '' ? $input['phone'] : null;
        $this->db->update('users', $input, 'id = :id', ['id' => $id]);
        [$old, $new] = AuditLog::diff($before, $input);
        AuditLog::log('update', 'profile', 'user', $id, 'Profil modifié', $old, $new);
        Session::flash('success', 'Profil mis à jour.');
        $this->redirect('profile');
    }

    public function password(): void
    {
        $id = (int) Auth::id();
        $user = $this->db->fetch('SELECT password_hash FROM users WHERE id = ?', [$id]);
        $current = (string) Request::post('current_password', '');
        $new = (string) Request::post('password', '');

        if (!password_verify($current, (string) $user['password_hash'])) {
            AuditLog::log('password_change_failed', 'security', 'user', $id, 'Échec du changement de mot de passe (mot de passe actuel incorrect)');
            Session::flash('danger', 'Le mot de passe actuel est incorrect.');
            $this->redirect('profile');
        }
        $errors = Auth::passwordErrors($new, (int) Settings::get('password_min_length', 10));
        if ($new !== (string) Request::post('password_confirm', '')) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }
        if (password_verify($new, (string) $user['password_hash'])) {
            $errors[] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
        }
        if ($errors) {
            Session::flash('danger', implode(' ', $errors));
            $this->redirect('profile');
        }
        $this->db->update('users', [
            'password_hash'       => Auth::hashPassword($new),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
        Session::regenerate();
        AuditLog::log('password_change', 'security', 'user', $id, 'Mot de passe modifié');
        Session::flash('success', 'Votre mot de passe a été modifié.');
        $this->redirect('profile');
    }

    /** Préférence de thème enregistrée depuis le bouton de la barre supérieure (AJAX). */
    public function theme(): void
    {
        $theme = Request::string('theme');
        if (!in_array($theme, ['auto', 'light', 'dark'], true)) {
            Response::json(['success' => false, 'message' => 'Thème invalide.'], 422);
        }
        $this->db->update('users', ['theme' => $theme], 'id = :id', ['id' => Auth::id()]);
        Response::json(['success' => true]);
    }
}
