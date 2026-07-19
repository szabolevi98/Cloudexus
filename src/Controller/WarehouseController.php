<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\WarehouseModel;

class WarehouseController extends BaseController
{
    private WarehouseModel $warehouses;

    public function __construct()
    {
        parent::__construct();
        $this->warehouses = new WarehouseModel();
        $this->activeMenu = 'warehouses';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => $_GET['status'] ?? '',
        ];
        $pager = new Paginator(30);

        $this->pageTitle = 'Raktárak és telephelyek';
        $this->render('warehouses/list.twig', [
            'warehouses' => $this->warehouses->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új raktár';
        $this->render('warehouses/form.twig', ['warehouse' => null]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($data['name'] === '') {
            $this->flashError('A raktár nevének megadása kötelező.');
            $this->redirect('/warehouses/create');
        }

        $this->warehouses->create($data);
        $this->flashSuccess('Raktár létrehozva.');
        $this->redirect('/warehouses');
    }

    public function editForm(int $id): void
    {
        $this->requireAuth();

        $warehouse = $this->warehouses->findById($id);
        if (!$warehouse) {
            $this->redirect('/warehouses');
        }

        $this->pageTitle = 'Raktár szerkesztése';
        $this->render('warehouses/form.twig', ['warehouse' => $warehouse]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($data['name'] === '') {
            $this->flashError('A raktár nevének megadása kötelező.');
            $this->redirect('/warehouses/' . $id . '/edit');
        }

        $this->warehouses->update($id, $data);
        $this->flashSuccess('Raktár frissítve.');
        $this->redirect('/warehouses');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->warehouses->delete($id);
        $this->flashSuccess('Raktár törölve.');
        $this->redirect('/warehouses');
    }

    private function collectInput(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
    }
}
