<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Model\Account\UserModel;

class UserController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserModel();
        $this->activeMenu = 'users';
    }

    public function list(): void
    {
        $this->requireAdmin();

        $filters = ['q' => trim($_GET['q'] ?? '')];
        $pager = new \Cloudexus\Core\Paginator(25);

        $this->pageTitle = 'Felhasználók';
        $this->render('users/list.twig', [
            'users' => $this->users->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function createForm(): void
    {
        $this->requireAdmin();

        $this->pageTitle = 'Új felhasználó';
        $this->render('users/form.twig', ['user' => null]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $data = $this->collectInput();
        $errors = $this->validate($data, null);

        if ($errors) {
            $this->flashError(implode(' ', $errors));
            $this->redirect('/users/create');
        }

        $this->users->create($data);
        $this->flashSuccess('Felhasználó létrehozva.');
        $this->redirect('/users');
    }

    public function editForm(int $id): void
    {
        $this->requireAdmin();

        $user = $this->users->findById($id);
        if (!$user) {
            $this->redirect('/users');
        }

        $this->pageTitle = 'Felhasználó szerkesztése';
        $this->render('users/form.twig', ['user' => $user]);
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $data = $this->collectInput();
        $errors = $this->validate($data, $id);

        if ($errors) {
            $this->flashError(implode(' ', $errors));
            $this->redirect('/users/' . $id . '/edit');
        }

        $this->users->update($id, $data);
        $this->flashSuccess('Felhasználó frissítve.');
        $this->redirect('/users');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        if ($id === Auth::id()) {
            $this->flashError('Saját fiókodat nem törölheted.');
            $this->redirect('/users');
        }

        $this->users->delete($id);
        $this->flashSuccess('Felhasználó törölve.');
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
