<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\PartnerModel;

class PartnerController extends BaseController
{
    private PartnerModel $partners;

    public function __construct()
    {
        parent::__construct();
        $this->partners = new PartnerModel();
        $this->activeMenu = 'partners';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'type' => $_GET['type'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];
        $pager = new \Cloudexus\Core\Paginator(25);

        $this->pageTitle = 'Partnerek';
        $this->render('partners/list.twig', [
            'partners' => $this->partners->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új partner';
        $this->render('partners/form.twig', ['partner' => null]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($data['name'] === '') {
            $this->flashError('A partner nevének megadása kötelező.');
            $this->redirect('/partners/create');
        }

        $this->partners->create($data);
        $this->flashSuccess('Partner létrehozva.');
        $this->redirect('/partners');
    }

    public function editForm(int $id): void
    {
        $this->requireAuth();

        $partner = $this->partners->findById($id);
        if (!$partner) {
            $this->redirect('/partners');
        }

        $this->pageTitle = 'Partner szerkesztése';
        $this->render('partners/form.twig', ['partner' => $partner]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($data['name'] === '') {
            $this->flashError('A partner nevének megadása kötelező.');
            $this->redirect('/partners/' . $id . '/edit');
        }

        $this->partners->update($id, $data);
        $this->flashSuccess('Partner frissítve.');
        $this->redirect('/partners');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->partners->delete($id);
        $this->flashSuccess('Partner törölve.');
        $this->redirect('/partners');
    }

    private function collectInput(): array
    {
        return [
            'type' => $_POST['type'] ?? 'customer',
            'name' => trim($_POST['name'] ?? ''),
            'tax_number' => trim($_POST['tax_number'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
    }
}
