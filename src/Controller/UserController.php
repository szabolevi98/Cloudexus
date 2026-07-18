<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Core\Session;
use Cloudexus\Model\Account\UserModel;

class UserController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserModel();
    }

    public function list(): void
    {
        $this->requireAdmin();

        $this->render('users/list.twig', [
            'users' => $this->users->all(),
            'success' => Session::flash('user_success'),
        ]);
    }

    public function createForm(): void
    {
        $this->requireAdmin();

        $this->render('users/form.twig', [
            'user' => null,
            'errors' => Session::flash('user_errors'),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $data = $this->collectInput();
        $errors = $this->validate($data, null);

        if ($errors) {
            Session::flash('user_errors', implode(' ', $errors));
            $this->redirect('/users/create');
        }

        $this->users->create($data);
        Session::flash('user_success', 'Felhasználó létrehozva.');
        $this->redirect('/users');
    }

    public function editForm(int $id): void
    {
        $this->requireAdmin();

        $user = $this->users->findById($id);
        if (!$user) {
            $this->redirect('/users');
        }

        $this->render('users/form.twig', [
            'user' => $user,
            'errors' => Session::flash('user_errors'),
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $data = $this->collectInput();
        $errors = $this->validate($data, $id);

        if ($errors) {
            Session::flash('user_errors', implode(' ', $errors));
            $this->redirect('/users/' . $id . '/edit');
        }

        $this->users->update($id, $data);
        Session::flash('user_success', 'Felhasználó frissítve.');
        $this->redirect('/users');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        if ($id === Auth::id()) {
            Session::flash('user_success', 'Saját fiókodat nem törölheted.');
            $this->redirect('/users');
        }

        $this->users->delete($id);
        Session::flash('user_success', 'Felhasználó törölve.');
        $this->redirect('/users');
    }

    private function collectInput(): array
    {
        return [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'full_name' => trim($_POST['full_name'] ?? ''),
            'role' => $_POST['role'] ?? 'user',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'password' => $_POST['password'] ?? '',
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];

        if ($data['username'] === '' || $data['email'] === '' || $data['full_name'] === '') {
            $errors[] = 'A felhasználónév, e-mail és név megadása kötelező.';
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Érvénytelen e-mail cím.';
        }

        if ($excludeId === null && $data['password'] === '') {
            $errors[] = 'Új felhasználóhoz jelszó megadása kötelező.';
        }

        if (!$errors && $this->users->usernameOrEmailExists($data['username'], $data['email'], $excludeId)) {
            $errors[] = 'A felhasználónév vagy e-mail cím már foglalt.';
        }

        return $errors;
    }
}
