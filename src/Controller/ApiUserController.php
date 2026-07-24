<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Account\ApiUserModel;

class ApiUserController extends BaseController
{
    private ApiUserModel $apiUsers;

    public function __construct()
    {
        parent::__construct();
        $this->apiUsers = new ApiUserModel();
        $this->activeMenu = 'api-users';
    }

    public function list(): void
    {
        $this->requireAdmin();

        $this->pageTitle = 'API felhasználók';
        $this->render('api-users/list.twig', ['api_users' => $this->apiUsers->all()]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError('Az API-felhasználó nevének megadása kötelező.');
        } else {
            $this->apiUsers->create($name);
            $this->flashSuccess('API-felhasználó létrehozva, a token az alábbi listában látható.');
        }
        $this->redirect('/api-users');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError('Az API-felhasználó nevének megadása kötelező.');
        } else {
            $this->apiUsers->rename($id, $name);
            $this->flashSuccess('API-felhasználó frissítve.');
        }
        $this->redirect('/api-users');
    }

    public function toggle(int $id): void
    {
        $this->requireAdmin();

        $user = $this->apiUsers->findById($id);
        if ($user) {
            $this->apiUsers->setActive($id, !$user['is_active']);
            $this->flashSuccess($user['is_active'] ? 'API-felhasználó inaktiválva.' : 'API-felhasználó aktiválva.');
        }
        $this->redirect('/api-users');
    }

    public function regenerate(int $id): void
    {
        $this->requireAdmin();

        if ($this->apiUsers->findById($id)) {
            $this->apiUsers->regenerateToken($id);
            $this->flashSuccess('Új token generálva. A régi token azonnal érvénytelen.');
        }
        $this->redirect('/api-users');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $this->apiUsers->delete($id);
        $this->flashSuccess('API-felhasználó törölve.');
        $this->redirect('/api-users');
    }
}
