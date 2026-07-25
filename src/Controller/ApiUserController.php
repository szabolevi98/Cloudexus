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

        $this->pageTitle = $this->t('api_users.list_title');
        $this->render('api-users/list.twig', ['api_users' => $this->apiUsers->all()]);
    }

    public function docs(): void
    {
        $this->requireAdmin();

        $this->activeMenu = 'api-docs';
        $this->pageTitle = $this->t('api_users.docs_title');
        $this->render('api-docs.twig');
    }

    public function create(): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError($this->t('api_users.name_required'));
        } else {
            $this->apiUsers->create($name);
            $this->flashSuccess($this->t('api_users.created'));
        }
        $this->redirect('/api-users');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError($this->t('api_users.name_required'));
        } else {
            $this->apiUsers->rename($id, $name);
            $this->flashSuccess($this->t('api_users.updated'));
        }
        $this->redirect('/api-users');
    }

    public function toggle(int $id): void
    {
        $this->requireAdmin();

        $user = $this->apiUsers->findById($id);
        if ($user) {
            $this->apiUsers->setActive($id, !$user['is_active']);
            $this->flashSuccess($user['is_active'] ? $this->t('api_users.deactivated') : $this->t('api_users.activated'));
        }
        $this->redirect('/api-users');
    }

    public function regenerate(int $id): void
    {
        $this->requireAdmin();

        if ($this->apiUsers->findById($id)) {
            $this->apiUsers->regenerateToken($id);
            $this->flashSuccess($this->t('api_users.token_regenerated'));
        }
        $this->redirect('/api-users');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $this->apiUsers->delete($id);
        $this->flashSuccess($this->t('api_users.deleted'));
        $this->redirect('/api-users');
    }
}
