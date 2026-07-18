<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\ParameterNameModel;

class ParameterNameController extends BaseController
{
    private ParameterNameModel $names;

    public function __construct()
    {
        parent::__construct();
        $this->names = new ParameterNameModel();
        $this->activeMenu = 'parameter-names';
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

        $this->pageTitle = 'Paraméternevek';
        $this->render('parameter-names/list.twig', ['names' => $this->names->all()]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError('A paraméternév megadása kötelező.');
        } elseif ($this->names->exists($name)) {
            $this->flashError('Ez a paraméternév már létezik.');
        } else {
            $this->names->create($name);
            $this->flashSuccess('Paraméternév hozzáadva.');
        }
        $this->redirect('/parameter-names');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flashError('A paraméternév megadása kötelező.');
        } elseif ($this->names->exists($name, $id)) {
            $this->flashError('Ez a paraméternév már létezik.');
        } else {
            $this->names->update($id, $name);
            $this->flashSuccess('Paraméternév frissítve.');
        }
        $this->redirect('/parameter-names');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $this->names->delete($id);
        $this->flashSuccess('Paraméternév törölve.');
        $this->redirect('/parameter-names');
    }
}
