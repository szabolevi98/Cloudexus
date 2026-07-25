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

        $this->pageTitle = $this->t('users.list_title');
        $this->render('users/list.twig', [
            'users' => $this->users->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function createForm(): void
    {
        $this->requireAdmin();

        $this->pageTitle = $this->t('users.new');
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
        $this->flashSuccess($this->t('users.created'));
        $this->redirect('/users');
    }

    public function editForm(int $id): void
    {
        $this->requireAdmin();

        $user = $this->users->findById($id);
        if (!$user) {
            $this->redirect('/users');
        }

        $this->pageTitle = $this->t('users.edit');
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
        $this->flashSuccess($this->t('users.updated'));
        $this->redirect('/users');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        if ($id === Auth::id()) {
            $this->flashError($this->t('users.cannot_delete_self'));
            $this->redirect('/users');
        }

        $this->users->delete($id);
        $this->flashSuccess($this->t('users.deleted'));
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
            $errors[] = $this->t('users.required_fields');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->t('users.invalid_email');
        }

        if ($excludeId === null && $data['password'] === '') {
            $errors[] = $this->t('users.password_required');
        }

        if (!$errors && $this->users->usernameOrEmailExists($data['username'], $data['email'], $excludeId)) {
            $errors[] = $this->t('users.username_email_taken');
        }

        return $errors;
    }
}
