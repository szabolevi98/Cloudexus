<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Model\Core\PartnerModel;
use Cloudexus\Model\Crm\PartnerActivityModel;

class PartnerController extends BaseController
{
    private PartnerModel $partners;
    private PartnerActivityModel $activities;

    public function __construct()
    {
        parent::__construct();
        $this->partners = new PartnerModel();
        $this->activities = new PartnerActivityModel();
        $this->activeMenu = 'partners';
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $partner = $this->partners->findById($id);
        if (!$partner) {
            $this->redirect('/partners');
        }

        $this->pageTitle = $partner['name'];
        $this->render('partners/show.twig', [
            'partner' => $partner,
            'activities' => $this->activities->forPartner($id),
        ]);
    }

    public function addActivity(int $id): void
    {
        $this->requireAuth();

        if (!$this->partners->findById($id)) {
            $this->redirect('/partners');
        }

        $subject = trim($_POST['subject'] ?? '');
        if ($subject === '') {
            $this->flashError('A tárgy megadása kötelező.');
            $this->redirect('/partners/' . $id);
        }

        $this->activities->create([
            'partner_id' => $id,
            'type' => in_array($_POST['type'] ?? '', ['call', 'email', 'meeting', 'note', 'offer'], true) ? $_POST['type'] : 'note',
            'subject' => $subject,
            'note' => trim($_POST['note'] ?? ''),
            'activity_date' => ($_POST['activity_date'] ?? '') !== '' ? str_replace('T', ' ', $_POST['activity_date']) . ':00' : date('Y-m-d H:i:s'),
            'created_by' => Auth::id(),
        ]);

        $this->flashSuccess('Bejegyzés hozzáadva.');
        $this->redirect('/partners/' . $id);
    }

    public function deleteActivity(int $id, int $activityId): void
    {
        $this->requireAuth();

        $activity = $this->activities->findById($activityId);
        if ($activity && (int) $activity['partner_id'] === $id) {
            $this->activities->delete($activityId);
            $this->flashSuccess('Bejegyzés törölve.');
        }

        $this->redirect('/partners/' . $id);
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

    public function export(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'type' => $_GET['type'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];
        $pager = new \Cloudexus\Core\Paginator(1000000);
        $rows = $this->partners->paginate($filters, $pager);

        $typeLabels = ['customer' => 'vevő', 'supplier' => 'szállító', 'both' => 'vevő+szállító'];

        \Cloudexus\Core\CsvExporter::download(
            'partnerek',
            ['Név', 'Típus', 'Adószám', 'E-mail', 'Telefon', 'Cím', 'Aktív'],
            array_map(fn($p) => [
                $p['name'], $typeLabels[$p['type']] ?? $p['type'], $p['tax_number'] ?? '',
                $p['email'] ?? '', $p['phone'] ?? '', $p['address'] ?? '', $p['is_active'] ? 'igen' : 'nem',
            ], $rows)
        );
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
