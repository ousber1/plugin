<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Connexion / déconnexion.
 * (Réinitialisation du mot de passe et changement de mot de passe : Phase 2.)
 */
final class AuthController extends Controller
{
    public function showLogin(): void
    {
        Response::noCache();
        $this->view('auth/login', ['title' => 'Connexion'], 'auth');
    }

    public function login(): void
    {
        $identifier = Request::string('identifier');
        $password = (string) Request::post('password', '');

        if ($identifier === '' || $password === '') {
            Session::flashInput(['identifier' => $identifier]);
            Session::flash('danger', 'Veuillez saisir votre identifiant et votre mot de passe.');
            $this->redirect('login');
        }

        [$ok, $message] = Auth::attempt($identifier, $password);
        if (!$ok) {
            Session::flashInput(['identifier' => $identifier]);
            Session::flash('danger', $message);
            $this->redirect('login');
        }

        Session::flash('success', $message);
        $intended = (string) Session::get('_intended', '');
        Session::forget('_intended');
        // Uniquement une route interne valide (anti open-redirect)
        $this->redirect(preg_match('#^[a-z0-9_\-/]*$#', $intended) ? $intended : '');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::start();
        Session::flash('success', 'Vous êtes déconnecté.');
        $this->redirect('login');
    }
}
