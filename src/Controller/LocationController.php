<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\LocationModel;
use Cloudexus\Model\Core\WarehouseModel;

class LocationController extends BaseController
{
    private LocationModel $locations;
    private WarehouseModel $warehouses;

    public function __construct()
    {
        parent::__construct();
        $this->locations = new LocationModel();
        $this->warehouses = new WarehouseModel();
        $this->activeMenu = 'locations';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'warehouse_id' => (int) ($_GET['warehouse_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
        ];
        $pager = new Paginator(30);

        $this->pageTitle = 'Tárhelyek és polcok';
        $this->render('locations/list.twig', [
            'locations' => $this->locations->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'warehouses' => $this->warehouses->all(),
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új tárhely';
        $this->render('locations/form.twig', [
            'location' => null,
            'warehouses' => $this->warehouses->activeList(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $data = $this->collectInput();
        if ($error = $this->validate($data, null)) {
            $this->flashError($error);
            $this->redirect('/locations/create');
        }

        $this->locations->create($data);
        $this->flashSuccess('Tárhely létrehozva.');
        $this->redirect('/locations');
    }

    public function editForm(int $id): void
    {
        $this->requireAuth();

        $location = $this->locations->findById($id);
        if (!$location) {
            $this->redirect('/locations');
        }

        $this->pageTitle = 'Tárhely szerkesztése';
        $this->render('locations/form.twig', [
            'location' => $location,
            'warehouses' => $this->warehouses->activeList(),
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        $data = $this->collectInput();
        if ($error = $this->validate($data, $id)) {
            $this->flashError($error);
            $this->redirect('/locations/' . $id . '/edit');
        }

        $this->locations->update($id, $data);
        $this->flashSuccess('Tárhely frissítve.');
        $this->redirect('/locations');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->locations->delete($id);
        $this->flashSuccess('Tárhely törölve.');
        $this->redirect('/locations');
    }

    private function collectInput(): array
    {
        return [
            'warehouse_id' => (int) ($_POST['warehouse_id'] ?? 0),
            'code' => trim($_POST['code'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): ?string
    {
        if ($data['warehouse_id'] <= 0 || $data['code'] === '') {
            return 'A raktár és a helykód megadása kötelező.';
        }
        if ($this->locations->codeExists($data['warehouse_id'], $data['code'], $excludeId)) {
            return 'Ez a helykód már létezik ebben a raktárban.';
        }
        return null;
    }
}
