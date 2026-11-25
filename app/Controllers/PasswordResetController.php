<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;

/**
 * Mot de passe oublié.
 * - Jeton aléatoire de 256 bits envoyé par e-mail ; seule son empreinte SHA-256 est stockée.
 * - Validité 60 minutes, usage unique ; les anciens jetons sont invalidés.
 * - Réponse identique que l'adresse existe ou non (pas d'énumération de comptes).
 * - 3 demandes maximum par heure et par adresse / 10 par IP.
 */
final class PasswordResetController extends Controller
{
    private const TTL_MINUTES = 60;

    public function showForgot(): void
    {
        Response::noCache();
        $this->view('auth/forgot', ['title' => 'Mot de passe oublié'], 'auth');
    }

    public function sendLink(): void
    {
        $email = mb_strtolower(Request::string('email'));
        $generic = 'Si un compte actif correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé. Il est valable ' . self::TTL_MINUTES . ' minutes.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('danger', 'Veuillez saisir une adresse e-mail valide.');
            $this->redirect('password/forgot');
        }

        $ip = Request::ip();
        $ipCount = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM password_resets WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)',
            [$ip]
        );
        $user = $this->db->fetch(
            'SELECT id, full_name, email FROM users WHERE LOWER(email) = ? AND is_active = 1 AND deleted_at IS NULL',
            [$email]
        );

        if ($user && $ipCount < 10) {
            $userCount = (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)',
                [$user['id']]
            );
            if ($userCount < 3) {
                $token = bin2hex(random_bytes(32));
                $this->db->transaction(function () use ($user, $token, $ip): void {
                    $this->db->query('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
                    $this->db->insert('password_resets', [
                        'user_id'    => $user['id'],
                        'token_hash' => hash('sha256', $token),
                        'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
                        'ip_address' => $ip,
                    ]);
                });
                $link = url('password/reset', ['token' => $token]);
                $mailer = new Mailer();
                $sent = $mailer->send(
                    (string) $user['email'],
                    'Réinitialisation de votre mot de passe — ' . Settings::get('business_name', 'HO CREATIVE'),
                    Mailer::template('Réinitialisation du mot de passe',
                        '<p>Bonjour ' . e($user['full_name']) . ',</p>'
                        . '<p>Une demande de réinitialisation du mot de passe a été effectuée pour votre compte.</p>'
                        . '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#0b0b0c;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Choisir un nouveau mot de passe</a></p>'
                        . '<p style="color:#71717a;font-size:13px">Ce lien est valable ' . self::TTL_MINUTES . ' minutes et ne peut être utilisé qu\'une fois. '
                        . 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.</p>')
                );
                AuditLog::log('password_reset_request', 'security', 'user', (int) $user['id'],
                    $sent ? 'Lien de réinitialisation envoyé' : 'Échec d\'envoi du lien : ' . $mailer->lastError(), null, null, (int) $user['id']);
            }
        }
        // Enregistre aussi les demandes pour adresses inconnues (limitation par IP)
        if (!$user) {
            $this->db->query(
                'INSERT INTO login_attempts (identifier, ip_address, success, user_agent) VALUES (?, ?, 0, ?)',
                ['reset:' . mb_substr($email, 0, 180), $ip, Request::userAgent()]
            );
        }
        Session::flash('success', $generic);
        $this->redirect('login');
    }

    public function showReset(): void
    {
        Response::noCache();
        $token = Request::string('token');
        if (!$this->findValid($token)) {
            Session::flash('danger', 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.');
            $this->redirect('password/forgot');
        }
        $this->view('auth/reset', [
            'title' => 'Nouveau mot de passe',
            'token' => $token,
            'minLength' => (int) Settings::get('password_min_length', 10),
        ], 'auth');
    }

    public function reset(): void
    {
        $token = Request::string('token');
        $reset = $this->findValid($token);
        if (!$reset) {
            Session::flash('danger', 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.');
            $this->redirect('password/forgot');
        }
        $password = (string) Request::post('password', '');
        $errors = Auth::passwordErrors($password, (int) Settings::get('password_min_length', 10));
        if ($password !== (string) Request::post('password_confirm', '')) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }
        if ($errors) {
            Session::flash('danger', implode(' ', $errors));
            $this->redirect('password/reset', ['token' => $token]);
        }
        $this->db->transaction(function () use ($reset, $password): void {
            $this->db->update('users', [
                'password_hash'       => Auth::hashPassword($password),
                'password_changed_at' => date('Y-m-d H:i:s'),
                'failed_attempts'     => 0,
                'locked_until'        => null,
            ], 'id = :id', ['id' => $reset['user_id']]);
            $this->db->query('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$reset['user_id']]);
        });
        AuditLog::log('password_reset', 'security', 'user', (int) $reset['user_id'], 'Mot de passe réinitialisé via lien e-mail', null, null, (int) $reset['user_id']);
        Session::flash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
        $this->redirect('login');
    }

    private function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return $this->db->fetch(
            'SELECT pr.* FROM password_resets pr JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                AND u.is_active = 1 AND u.deleted_at IS NULL',
            [hash('sha256', $token)]
        );
    }
}
