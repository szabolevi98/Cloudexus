<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\LocationModel;
use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Core\StockMovementModel;
use Cloudexus\Model\Core\WarehouseModel;

class StockController extends BaseController
{
    private StockMovementModel $movements;
    private WarehouseModel $warehouses;
    private ProductModel $products;
    private LocationModel $locations;

    public function __construct()
    {
        parent::__construct();
        $this->movements = new StockMovementModel();
        $this->warehouses = new WarehouseModel();
        $this->products = new ProductModel();
        $this->locations = new LocationModel();
    }

    public function overview(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'warehouse_id' => (int) ($_GET['warehouse_id'] ?? 0),
            'location_id' => (int) ($_GET['location_id'] ?? 0),
        ];
        $pager = new Paginator(25);

        $this->activeMenu = 'stock-overview';
        $this->pageTitle = 'Raktárkészlet';
        $this->render('stock/overview.twig', [
            'rows' => $this->movements->overview($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'warehouses' => $this->warehouses->activeList(),
            'locations' => $this->locations->activeWithWarehouse(),
        ]);
    }

    public function inList(): void
    {
        $this->requireAuth();

        [$filters, $pager, $rows] = $this->movementListData('in');

        $this->activeMenu = 'stock-in';
        $this->pageTitle = 'Raktári bevét';
        $this->render('stock/in.twig', [
            'movements' => $rows,
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'warehouses' => $this->warehouses->activeList(),
            'products' => $this->products->all(),
            'locations' => $this->locations->activeWithWarehouse(),
        ]);
    }

    public function inCreate(): void
    {
        $this->requireAuth();
        $this->createMovement('in', '/stock/in');
    }

    public function outList(): void
    {
        $this->requireAuth();

        [$filters, $pager, $rows] = $this->movementListData('out');

        $this->activeMenu = 'stock-out';
        $this->pageTitle = 'Raktári kiadás';
        $this->render('stock/out.twig', [
            'movements' => $rows,
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'warehouses' => $this->warehouses->activeList(),
            'products' => $this->products->all(),
            'locations' => $this->locations->activeWithWarehouse(),
        ]);
    }

    public function outCreate(): void
    {
        $this->requireAuth();
        $this->createMovement('out', '/stock/out');
    }

    public function transferForm(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'warehouse_id' => (int) ($_GET['warehouse_id'] ?? 0),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $pager = new Paginator(20);

        $this->activeMenu = 'stock-transfer';
        $this->pageTitle = 'Raktárközi átadás';
        $this->render('stock/transfer.twig', [
            'warehouses' => $this->warehouses->activeList(),
            'products' => $this->products->all(),
            'locations' => $this->locations->activeWithWarehouse(),
            'transfers' => $this->movements->paginateTransfers($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
        ]);
    }

    public function transferCreate(): void
    {
        $this->requireAuth();

        $fromId = (int) ($_POST['from_warehouse_id'] ?? 0);
        $toId = (int) ($_POST['to_warehouse_id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (float) str_replace(',', '.', $_POST['quantity'] ?? '0');
        $note = trim($_POST['note'] ?? '');

        if ($fromId <= 0 || $toId <= 0 || $productId <= 0 || $quantity <= 0) {
            $this->flashError('Forrás- és célraktár, termék és pozitív mennyiség megadása kötelező.');
            $this->redirect('/stock/transfer');
        }

        if ($fromId === $toId) {
            $this->flashError('A forrás- és célraktár nem lehet azonos.');
            $this->redirect('/stock/transfer');
        }

        $available = $this->movements->availableQuantity($productId, $fromId);
        if ($quantity > $available) {
            $this->flashError(sprintf('Nincs elég készlet a forrásraktárban: elérhető %s, kért %s.', $available, $quantity));
            $this->redirect('/stock/transfer');
        }

        $from = $this->warehouses->findById($fromId);
        $to = $this->warehouses->findById($toId);

        $this->movements->transfer(
            $fromId,
            $toId,
            $productId,
            $quantity,
            'Raktárközi átadás: ' . ($from['name'] ?? $fromId) . ' → ' . ($to['name'] ?? $toId) . ($note !== '' ? ' — ' . $note : ''),
            Auth::id(),
            (int) ($_POST['from_location_id'] ?? 0) ?: null,
            (int) ($_POST['to_location_id'] ?? 0) ?: null
        );

        $this->flashSuccess('Raktárközi átadás rögzítve.');
        $this->redirect('/stock/transfer');
    }

    public function barcodeForm(): void
    {
        $this->requireAuth();

        $this->activeMenu = 'stock-barcode';
        $this->pageTitle = 'Vonalkód gyűjtő';
        $this->render('stock/barcode.twig', [
            'warehouses' => $this->warehouses->activeList(),
            'locations' => $this->locations->activeWithWarehouse(),
        ]);
    }

    /** JSON lookup endpoint: resolves a scanned barcode or SKU to a product. */
    public function barcodeLookup(): void
    {
        $this->requireAuth();

        $code = trim($_GET['code'] ?? '');
        $product = $code !== '' ? $this->products->findByCode($code) : null;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($product
            ? ['found' => true, 'product' => [
                'id' => (int) $product['id'],
                'sku' => $product['sku'],
                'name' => $product['name'],
                'unit' => $product['unit'],
            ]]
            : ['found' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Books all collected barcode rows as stock movements in one batch. */
    public function barcodeSubmit(): void
    {
        $this->requireAuth();

        $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
        $direction = $_POST['direction'] === 'out' ? 'out' : 'in';
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        $items = [];
        foreach ($productIds as $index => $productId) {
            $productId = (int) $productId;
            $quantity = (float) str_replace(',', '.', $quantities[$index] ?? '0');
            if ($productId > 0 && $quantity > 0) {
                $items[$productId] = ($items[$productId] ?? 0) + $quantity;
            }
        }

        if ($warehouseId <= 0 || empty($items)) {
            $this->flashError('Raktár és legalább egy beolvasott tétel szükséges.');
            $this->redirect('/stock/barcode');
        }

        if ($direction === 'out') {
            foreach ($items as $productId => $quantity) {
                $available = $this->movements->availableQuantity($productId, $warehouseId);
                if ($quantity > $available) {
                    $product = $this->products->findById($productId);
                    $this->flashError(sprintf('Nincs elég készlet: %s (elérhető %s, kért %s).', $product['sku'] ?? $productId, $available, $quantity));
                    $this->redirect('/stock/barcode');
                }
            }
        }

        $locationId = (int) ($_POST['location_id'] ?? 0) ?: null;
        foreach ($items as $productId => $quantity) {
            $this->movements->create([
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'type' => $direction,
                'quantity' => $quantity,
                'note' => 'Vonalkód gyűjtő',
                'created_by' => Auth::id(),
            ]);
        }

        $this->flashSuccess(sprintf('%d tétel %s könyvelve a vonalkód gyűjtőből.', count($items), $direction === 'in' ? 'bevétként' : 'kiadásként'));
        $this->redirect('/stock/barcode');
    }

    /** @return array{0: array, 1: Paginator, 2: array} */
    private function movementListData(string $type): array
    {
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'warehouse_id' => (int) ($_GET['warehouse_id'] ?? 0),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $pager = new Paginator(20);

        return [$filters, $pager, $this->movements->paginateByType($type, $filters, $pager)];
    }

    private function createMovement(string $type, string $redirectPath): void
    {
        $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (float) str_replace(',', '.', $_POST['quantity'] ?? '0');
        $note = trim($_POST['note'] ?? '');

        if ($warehouseId <= 0 || $productId <= 0 || $quantity <= 0) {
            $this->flashError('Raktár, termék és pozitív mennyiség megadása kötelező.');
            $this->redirect($redirectPath);
        }

        if ($type === 'out') {
            $available = $this->movements->availableQuantity($productId, $warehouseId);

            if ($quantity > $available) {
                $this->flashError(sprintf('Nincs elég készlet: elérhető %s db, kért %s db.', $available, $quantity));
                $this->redirect($redirectPath);
            }
        }

        $this->movements->create([
            'warehouse_id' => $warehouseId,
            'location_id' => (int) ($_POST['location_id'] ?? 0) ?: null,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);

        $this->flashSuccess($type === 'in' ? 'Raktári bevét rögzítve.' : 'Raktári kiadás rögzítve.');
        $this->redirect($redirectPath);
    }
}
