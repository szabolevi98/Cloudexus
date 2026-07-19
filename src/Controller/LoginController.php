<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Core\Recaptcha;
use Cloudexus\Core\Session;

class LoginController extends BaseController
{
    public function show(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        $this->render('login.twig', [
            'error' => Session::flash('login_error'),
            'recaptcha_site_key' => Recaptcha::siteKey(),
            'recaptcha_enabled' => Recaptcha::enabled(),
        ]);
    }

    public function submit(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $recaptchaToken = (string) ($_POST['recaptcha_token'] ?? '');

        if (!Recaptcha::verify($recaptchaToken, 'login')) {
            Session::flash('login_error', 'A biztonsági ellenőrzés sikertelen volt. Próbáld újra.');
            $this->redirect('/login');
        }

        if ($username === '' || $password === '' || !Auth::attempt($username, $password)) {
            Session::flash('login_error', 'Hibás felhasználónév/e-mail vagy jelszó.');
            $this->redirect('/login');
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
