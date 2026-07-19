<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\UnitModel;

class UnitController extends BaseController
{
    private UnitModel $units;

    public function __construct()
    {
        parent::__construct();
        $this->units = new UnitModel();
        $this->activeMenu = 'units';
    }

    public function list(): void
    {
        $this->requireAdmin();

        $filters = ['q' => trim($_GET['q'] ?? '')];
        $pager = new Paginator(30);

        $this->pageTitle = 'Mennyiségi egységek';
        $this->render('units/list.twig', [
            'units' => $this->units->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $data = $this->collectInput();
        if ($data['code'] === '' || $data['name'] === '') {
            $this->flashError('A kód és a név megadása kötelező.');
        } elseif ($this->units->codeExists($data['code'])) {
            $this->flashError('Ez a kód már létezik.');
        } else {
            $this->units->create($data);
            $this->flashSuccess('Mennyiségi egység hozzáadva.');
        }
        $this->redirect('/units');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $data = $this->collectInput();
        if ($data['code'] === '' || $data['name'] === '') {
            $this->flashError('A kód és a név megadása kötelező.');
        } elseif ($this->units->codeExists($data['code'], $id)) {
            $this->flashError('Ez a kód már létezik.');
        } else {
            $this->units->update($id, $data);
            $this->flashSuccess('Mennyiségi egység frissítve.');
        }
        $this->redirect('/units');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $this->units->delete($id);
        $this->flashSuccess('Mennyiségi egység törölve.');
        $this->redirect('/units');
    }

    private function collectInput(): array
    {
        return [
            'code' => trim($_POST['code'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];
    }
}
