<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Model\Account\UserModel;

class ProfileController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserModel();
    }

    public function show(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Profil';
        $this->render('profile.twig', [
            'user' => $this->users->findById(Auth::id()),
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();

        $user = $this->users->findById(Auth::id());
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flashError('A név és érvényes e-mail cím megadása kötelező.');
            $this->redirect('/profile');
        }

        if ($this->users->usernameOrEmailExists($user['username'], $email, (int) $user['id'])) {
            $this->flashError('Ez az e-mail cím már foglalt.');
            $this->redirect('/profile');
        }

        $password = '';
        if ($newPassword !== '') {
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $this->flashError('A jelenlegi jelszó nem megfelelő.');
                $this->redirect('/profile');
            }
            if (strlen($newPassword) < 8) {
                $this->flashError('Az új jelszónak legalább 8 karakteresnek kell lennie.');
                $this->redirect('/profile');
            }
            if ($newPassword !== $newPasswordConfirm) {
                $this->flashError('Az új jelszó és a megerősítés nem egyezik.');
                $this->redirect('/profile');
            }
            $password = $newPassword;
        }

        $this->users->update((int) $user['id'], [
            'username' => $user['username'],
            'email' => $email,
            'full_name' => $fullName,
            'role' => $user['role'],
            'is_active' => $user['is_active'],
            'password' => $password,
        ]);

        \Cloudexus\Core\Session::set('user_name', $fullName);

        $this->flashSuccess('Profil frissítve.' . ($password !== '' ? ' Az új jelszó a következő belépéstől érvényes.' : ''));
        $this->redirect('/profile');
    }
}
