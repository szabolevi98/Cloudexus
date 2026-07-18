<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\CategoryModel;
use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Core\StockMovementModel;
use Cloudexus\Model\Core\UnitModel;
use Cloudexus\Model\Core\WarehouseModel;

class ProductController extends BaseController
{
    private const UPLOAD_DIR = 'assets/uploads/products';
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private ProductModel $products;
    private CategoryModel $categories;
    private UnitModel $units;

    public function __construct()
    {
        parent::__construct();
        $this->products = new ProductModel();
        $this->categories = new CategoryModel();
        $this->units = new UnitModel();
        $this->activeMenu = 'products';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'category_id' => (int) ($_GET['category_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
        ];
        $pager = new Paginator(30);

        $this->pageTitle = 'Termékek';
        $this->render('products/list.twig', [
            'products' => $this->products->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'categories' => $this->categories->all(),
        ]);
    }

    public function export(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'category_id' => (int) ($_GET['category_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
        ];
        $pager = new Paginator(1000000);
        $rows = $this->products->paginate($filters, $pager);

        \Cloudexus\Core\CsvExporter::download(
            'termekek',
            ['Cikkszám', 'Vonalkód', 'Megnevezés', 'Kategória', 'Egység', 'Nettó ár', 'ÁFA %', 'Készlet', 'Min. készlet', 'Webshop', 'Aktív'],
            array_map(fn($p) => [
                $p['sku'], $p['barcode'] ?? '', $p['name'], $p['category_name'] ?? '',
                $p['unit'], $p['price'], $p['vat_rate'], $p['stock_qty'], $p['min_stock'],
                $p['is_webshop'] ? 'igen' : 'nem', $p['is_active'] ? 'igen' : 'nem',
            ], $rows)
        );
    }

    public function search(): void
    {
        $this->requireAuth();
        $this->json($this->products->search(trim($_GET['q'] ?? ''), (int) ($_GET['page'] ?? 1)));
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új termék';
        $this->render('products/form.twig', $this->formData(null));
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

        $id = $this->products->create($data);
        $this->handleImageUploads($id);
        $this->handleImageUrl($id);
        $this->handleOpeningStock($id);

        $this->flashSuccess('Termék létrehozva.');
        $this->redirect('/products/' . $id . '/edit');
    }

    public function editForm(int $id): void
    {
        $this->requireAuth();

        $product = $this->products->findFull($id);
        if (!$product) {
            $this->redirect('/products');
        }

        $this->pageTitle = 'Termék szerkesztése';
        $this->render('products/form.twig', $this->formData($product));
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
        $this->handleImageUploads($id);
        $this->handleImageUrl($id);

        $this->flashSuccess('Termék frissítve.');
        $this->redirect('/products/' . $id . '/edit');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        try {
            $this->products->delete($id);
            $this->flashSuccess('Termék törölve.');
        } catch (\PDOException $e) {
            // Van hozzá készletmozgás / bizonylat — ne töröljük, inkább inaktiváljuk.
            $this->flashError('A termék nem törölhető, mert kapcsolódik hozzá készletmozgás vagy bizonylat. Állítsd inkább inaktívra.');
        }

        $this->redirect('/products');
    }

    public function deleteImage(int $id, int $imageId): void
    {
        $this->requireAuth();

        $image = $this->products->findImage($imageId);
        if ($image && (int) $image['product_id'] === $id) {
            $file = dirname(__DIR__, 2) . '/web/' . $image['path'];
            if (is_file($file)) {
                @unlink($file);
            }
            $this->products->deleteImage($imageId);
            $this->flashSuccess('Kép törölve.');
        }

        $this->redirect('/products/' . $id . '/edit');
    }

    public function setPrimaryImage(int $id, int $imageId): void
    {
        $this->requireAuth();

        $image = $this->products->findImage($imageId);
        if ($image && (int) $image['product_id'] === $id) {
            $this->products->setPrimaryImage($imageId);
            $this->flashSuccess('Elsődleges kép beállítva.');
        }

        $this->redirect('/products/' . $id . '/edit');
    }

    private function formData(?array $product): array
    {
        return [
            'product' => $product,
            'units' => $this->units->all(),
            'warehouses' => (new WarehouseModel())->activeList(),
            // Előre kijelölt Select2 opciók (id + felirat) szerkesztéskor
            'category_options' => $product ? $this->categories->labelsForIds($product['category_ids']) : [],
            'related_options' => $product ? $this->products->labelsForIds($product['related_ids']) : [],
            'substitute_options' => $product ? $this->products->labelsForIds($product['substitute_ids']) : [],
        ];
    }

    private function collectInput(): array
    {
        // Nincs külön elsődleges kategória: a kiválasztott kategóriák elseje lesz
        // az elsődleges (category_id), a többi a kapcsolótáblába kerül.
        $categoryIds = array_values(array_filter(array_map('intval', $_POST['category_ids'] ?? [])));

        return [
            'sku' => trim($_POST['sku'] ?? ''),
            'barcode' => trim($_POST['barcode'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category_id' => $categoryIds[0] ?? 0,
            'category_ids' => $categoryIds,
            'unit' => trim($_POST['unit'] ?? 'db'),
            'price' => (float) str_replace(',', '.', $_POST['price'] ?? '0'),
            'vat_rate' => (float) str_replace(',', '.', $_POST['vat_rate'] ?? '27'),
            'min_stock' => (float) str_replace(',', '.', $_POST['min_stock'] ?? '0'),
            'width_mm' => $_POST['width_mm'] ?? '',
            'height_mm' => $_POST['height_mm'] ?? '',
            'depth_mm' => $_POST['depth_mm'] ?? '',
            'weight_g' => $_POST['weight_g'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_webshop' => isset($_POST['is_webshop']) ? 1 : 0,
            'attr_name' => $_POST['attr_name'] ?? [],
            'attr_value' => $_POST['attr_value'] ?? [],
            'related_ids' => $_POST['related_ids'] ?? [],
            'substitute_ids' => $_POST['substitute_ids'] ?? [],
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

    /** Saves any uploaded image files into web/assets/uploads/products and links them. */
    private function handleImageUploads(int $productId): void
    {
        if (empty($_FILES['images']) || !is_array($_FILES['images']['name'])) {
            return;
        }

        $uploadDir = dirname(__DIR__, 2) . '/web/' . self::UPLOAD_DIR;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['images']['name'] as $i => $name) {
            if (($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                $this->flashError('Csak kép tölthető fel (jpg, png, webp, gif).');
                continue;
            }
            if (($_FILES['images']['size'][$i] ?? 0) > 5 * 1024 * 1024) {
                $this->flashError('A kép mérete legfeljebb 5 MB lehet.');
                continue;
            }

            $filename = 'prd-' . $productId . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $target = $uploadDir . '/' . $filename;

            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target)) {
                $this->products->addImage($productId, self::UPLOAD_DIR . '/' . $filename);
            }
        }
    }

    /** Attaches an external image by URL (stored as-is; must be a valid http(s) URL). */
    private function handleImageUrl(int $productId): void
    {
        $url = trim($_POST['image_url'] ?? '');
        if ($url === '') {
            return;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url)) {
            $this->products->addImage($productId, $url);
        } else {
            $this->flashError('Érvénytelen kép URL.');
        }
    }

    private function handleOpeningStock(int $productId): void
    {
        $qty = (float) str_replace(',', '.', $_POST['opening_stock'] ?? '0');
        $warehouseId = (int) ($_POST['opening_warehouse_id'] ?? 0);

        if ($qty > 0 && $warehouseId > 0) {
            (new StockMovementModel())->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'type' => 'in',
                'quantity' => $qty,
                'note' => 'Nyitókészlet',
                'created_by' => Auth::id(),
            ]);
        }
    }
}
