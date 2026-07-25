<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\ParameterModel;

class ParameterController extends BaseController
{
    private ParameterModel $names;

    public function __construct()
    {
        parent::__construct();
        $this->names = new ParameterModel();
        $this->activeMenu = 'parameters';
    }

    /** Select2 AJAX endpoint (any authenticated user). */
    public function search(): void
    {
        $this->requireAuth();
        $this->json($this->names->search(trim($_GET['q'] ?? ''), (int) ($_GET['page'] ?? 1)));
    }

    public function list(): void
    {
        $this->requireAdmin();

        $filters = ['q' => trim($_GET['q'] ?? '')];
        $pager = new Paginator(30);

        $this->pageTitle = $this->t('parameters.list_title');
        $this->render('parameters/list.twig', [
            'names' => $this->names->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError($this->t('parameters.name_required'));
        } elseif ($this->names->exists($name)) {
            $this->flashError($this->t('parameters.name_exists'));
        } else {
            $this->names->create($name);
            $this->flashSuccess($this->t('parameters.created'));
        }
        $this->redirect('/parameters');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError($this->t('parameters.name_required'));
        } elseif ($this->names->exists($name, $id)) {
            $this->flashError($this->t('parameters.name_exists'));
        } else {
            $this->names->update($id, $name);
            $this->flashSuccess($this->t('parameters.updated'));
        }
        $this->redirect('/parameters');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $this->names->delete($id);
        $this->flashSuccess($this->t('parameters.deleted'));
        $this->redirect('/parameters');
    }
}
