<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\CategoryModel;

class CategoryController extends BaseController
{
    private CategoryModel $categories;

    public function __construct()
    {
        parent::__construct();
        $this->categories = new CategoryModel();
        $this->activeMenu = 'categories';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = ['q' => trim($_GET['q'] ?? '')];
        $pager = new \Cloudexus\Core\Paginator(25);

        $this->pageTitle = 'Termékkategóriák';
        $this->render('categories/list.twig', [
            'categories' => $this->categories->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'paths' => $this->categories->paths(),
        ]);
    }

    public function search(): void
    {
        $this->requireAuth();
        $this->json($this->categories->search(trim($_GET['q'] ?? ''), (int) ($_GET['page'] ?? 1)));
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új kategória';
        $this->render('categories/form.twig', [
            'category' => null,
            'categories' => $this->categories->all(),
            'paths' => $this->categories->paths(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($data['name'] === '') {
            $this->flashError('A kategória nevének megadása kötelező.');
            $this->redirect('/categories/create');
        }

        $this->categories->create($data);
        $this->flashSuccess('Kategória létrehozva.');
        $this->redirect('/categories');
    }

    public function editForm(int $id): void
    {
        $this->requireAuth();

        $category = $this->categories->findById($id);
        if (!$category) {
            $this->redirect('/categories');
        }

        $this->pageTitle = 'Kategória szerkesztése';
        $this->render('categories/form.twig', [
            'category' => $category,
            'categories' => $this->categories->all(),
            'paths' => $this->categories->paths(),
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($data['name'] === '') {
            $this->flashError('A kategória nevének megadása kötelező.');
            $this->redirect('/categories/' . $id . '/edit');
        }

        $this->categories->update($id, $data);
        $this->flashSuccess('Kategória frissítve.');
        $this->redirect('/categories');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->categories->delete($id);
        $this->flashSuccess('Kategória törölve.');
        $this->redirect('/categories');
    }

    private function collectInput(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'parent_id' => $_POST['parent_id'] ?? null,
        ];
    }
}
