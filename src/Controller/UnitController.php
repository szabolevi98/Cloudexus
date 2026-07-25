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

        $this->pageTitle = $this->t('units.list_title');
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
            $this->flashError($this->t('units.code_name_required'));
        } elseif ($this->units->codeExists($data['code'])) {
            $this->flashError($this->t('units.code_exists'));
        } else {
            $this->units->create($data);
            $this->flashSuccess($this->t('units.created'));
        }
        $this->redirect('/units');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $data = $this->collectInput();
        if ($data['code'] === '' || $data['name'] === '') {
            $this->flashError($this->t('units.code_name_required'));
        } elseif ($this->units->codeExists($data['code'], $id)) {
            $this->flashError($this->t('units.code_exists'));
        } else {
            $this->units->update($id, $data);
            $this->flashSuccess($this->t('units.updated'));
        }
        $this->redirect('/units');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $this->units->delete($id);
        $this->flashSuccess($this->t('units.deleted'));
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
