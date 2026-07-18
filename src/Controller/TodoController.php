<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Core\Paginator;
use Cloudexus\Model\Account\UserModel;
use Cloudexus\Model\Core\PartnerModel;
use Cloudexus\Model\Crm\TodoModel;

class TodoController extends BaseController
{
    private TodoModel $todos;

    public function __construct()
    {
        parent::__construct();
        $this->todos = new TodoModel();
        $this->activeMenu = 'todos';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => $_GET['status'] ?? 'open',
            'assigned_to' => (int) ($_GET['assigned_to'] ?? 0),
        ];
        $pager = new Paginator(25);

        $this->pageTitle = 'Teendők';
        $this->render('todos/list.twig', [
            'todos' => $this->todos->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'partners' => (new PartnerModel())->all(),
            'users' => (new UserModel())->all(),
            'today' => date('Y-m-d'),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $this->flashError('A teendő megnevezése kötelező.');
            $this->redirect('/todos');
        }

        $this->todos->create([
            'title' => $title,
            'due_date' => $_POST['due_date'] ?? null,
            'partner_id' => $_POST['partner_id'] ?? null,
            'assigned_to' => $_POST['assigned_to'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->flashSuccess('Teendő hozzáadva.');
        $this->redirect('/todos');
    }

    public function toggle(int $id): void
    {
        $this->requireAuth();
        $this->todos->toggle($id);
        $this->redirect($_POST['return'] ?? '/todos');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();
        $this->todos->delete($id);
        $this->flashSuccess('Teendő törölve.');
        $this->redirect('/todos');
    }
}
