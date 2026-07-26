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

        $this->pageTitle = $this->t('categories.list_title');
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

        $this->pageTitle = $this->t('categories.new');
        $this->render('categories/form.twig', [
            'category' => null,
            'descriptions' => [],
            'categories' => $this->categories->all(),
            'paths' => $this->categories->paths(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($this->defaultText($data['name']) === '') {
            $this->flashError($this->t('categories.name_required'));
            $this->redirect('/categories/create');
        }

        $this->categories->create($data);
        $this->flashSuccess($this->t('categories.created'));
        $this->redirect('/categories');
    }

    public function editForm(int $id): void
    {
        $this->requireAuth();

        $category = $this->categories->findById($id);
        if (!$category) {
            $this->redirect('/categories');
        }

        $this->pageTitle = $this->t('categories.edit_title');
        $this->render('categories/form.twig', [
            'category' => $category,
            'descriptions' => $this->categories->descriptions($id),
            'categories' => $this->categories->all(),
            'paths' => $this->categories->paths(),
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        $data = $this->collectInput();

        if ($this->defaultText($data['name']) === '') {
            $this->flashError($this->t('categories.name_required'));
            $this->redirect('/categories/' . $id . '/edit');
        }

        $this->categories->update($id, $data);
        $this->flashSuccess($this->t('categories.updated'));
        $this->redirect('/categories');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->categories->delete($id);
        $this->flashSuccess($this->t('categories.deleted'));
        $this->redirect('/categories');
    }

    private function collectInput(): array
    {
        return [
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
            'parent_id' => $_POST['parent_id'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    /**
     * Nyelvenkénti szövegek a POST-ból: name[<nyelv id>].
     *
     * @return array<int, string>
     */
    private function localized(string $field): array
    {
        $out = [];
        foreach ((array) ($_POST[$field] ?? []) as $languageId => $value) {
            $languageId = (int) $languageId;
            if ($languageId > 0) {
                $out[$languageId] = trim((string) $value);
            }
        }

        return $out;
    }

    /** A szöveg az alapnyelven — ez a kötelező kitöltés feltétele. */
    private function defaultText(array $localized): string
    {
        return $localized[\Cloudexus\Core\Language::defaultId()] ?? '';
    }
}
