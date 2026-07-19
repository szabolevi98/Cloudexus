<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\CustomerGroupModel;

class CustomerGroupController extends BaseController
{
    private CustomerGroupModel $groups;

    public function __construct()
    {
        parent::__construct();
        $this->groups = new CustomerGroupModel();
        $this->activeMenu = 'customer-groups';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = ['q' => trim($_GET['q'] ?? '')];
        $pager = new Paginator(30);

        $this->pageTitle = 'Vevőcsoportok';
        $this->render('customer-groups/list.twig', [
            'groups' => $this->groups->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $this->flashError('A vevőcsoport nevének megadása kötelező.');
        } elseif ($this->groups->exists($name)) {
            $this->flashError('Ez a vevőcsoport név már létezik.');
        } else {
            $this->groups->create(['name' => $name, 'description' => $description]);
            $this->flashSuccess('Vevőcsoport hozzáadva.');
        }

        $this->redirect('/customer-groups');
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $this->flashError('A vevőcsoport nevének megadása kötelező.');
        } elseif ($this->groups->exists($name, $id)) {
            $this->flashError('Ez a vevőcsoport név már létezik.');
        } else {
            $this->groups->update($id, ['name' => $name, 'description' => $description]);
            $this->flashSuccess('Vevőcsoport frissítve.');
        }

        $this->redirect('/customer-groups');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->groups->delete($id);
        $this->flashSuccess('Vevőcsoport törölve.');
        $this->redirect('/customer-groups');
    }
}
