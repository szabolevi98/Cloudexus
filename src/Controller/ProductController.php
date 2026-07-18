<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\CategoryModel;
use Cloudexus\Model\Core\ProductModel;

class ProductController extends BaseController
{
    private ProductModel $products;
    private CategoryModel $categories;

    public function __construct()
    {
        parent::__construct();
        $this->products = new ProductModel();
        $this->categories = new CategoryModel();
        $this->activeMenu = 'products';
    }

    public function list(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Termékek';
        $this->render('products/list.twig', [
            'products' => $this->products->all(),
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új termék';
        $this->render('products/form.twig', [
            'product' => null,
            'categories' => $this->categories->all(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $data = $this->collectInput();
        $errors = $this->validate($data, null);

        if ($errors) {
            $this->flashError(implode(' ', $errors));
            $this->redirect('/products/create');
        }

        $this->products->create($data);
        $this->flashSuccess('Termék létrehozva.');
        $this->redirect('/products');
    }

    public function editForm(int $id): void
    {
        $this->requireAuth();

        $product = $this->products->findById($id);
        if (!$product) {
            $this->redirect('/products');
        }

        $this->pageTitle = 'Termék szerkesztése';
        $this->render('products/form.twig', [
            'product' => $product,
            'categories' => $this->categories->all(),
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        $data = $this->collectInput();
        $errors = $this->validate($data, $id);

        if ($errors) {
            $this->flashError(implode(' ', $errors));
            $this->redirect('/products/' . $id . '/edit');
        }

        $this->products->update($id, $data);
        $this->flashSuccess('Termék frissítve.');
        $this->redirect('/products');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->products->delete($id);
        $this->flashSuccess('Termék törölve.');
        $this->redirect('/products');
    }

    private function collectInput(): array
    {
        return [
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'category_id' => $_POST['category_id'] ?? null,
            'unit' => trim($_POST['unit'] ?? 'db'),
            'price' => (float) str_replace(',', '.', $_POST['price'] ?? '0'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];

        if ($data['sku'] === '' || $data['name'] === '') {
            $errors[] = 'A cikkszám és a megnevezés megadása kötelező.';
        }

        if (!$errors && $this->products->skuExists($data['sku'], $excludeId)) {
            $errors[] = 'Ez a cikkszám már foglalt.';
        }

        return $errors;
    }
}
