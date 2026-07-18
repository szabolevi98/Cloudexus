<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Core\StockMovementModel;
use Cloudexus\Model\Core\WarehouseModel;

class StockController extends BaseController
{
    private StockMovementModel $movements;
    private WarehouseModel $warehouses;
    private ProductModel $products;

    public function __construct()
    {
        parent::__construct();
        $this->movements = new StockMovementModel();
        $this->warehouses = new WarehouseModel();
        $this->products = new ProductModel();
    }

    public function overview(): void
    {
        $this->requireAuth();

        $this->activeMenu = 'stock-overview';
        $this->pageTitle = 'Raktárkészlet';
        $this->render('stock/overview.twig', [
            'rows' => $this->movements->overview(),
        ]);
    }

    public function inList(): void
    {
        $this->requireAuth();

        $this->activeMenu = 'stock-in';
        $this->pageTitle = 'Raktári bevét';
        $this->render('stock/in.twig', [
            'movements' => $this->movements->listByType('in'),
            'warehouses' => $this->warehouses->activeList(),
            'products' => $this->products->all(),
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

        $this->activeMenu = 'stock-out';
        $this->pageTitle = 'Raktári kiadás';
        $this->render('stock/out.twig', [
            'movements' => $this->movements->listByType('out'),
            'warehouses' => $this->warehouses->activeList(),
            'products' => $this->products->all(),
        ]);
    }

    public function outCreate(): void
    {
        $this->requireAuth();
        $this->createMovement('out', '/stock/out');
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
            $stmt = \Cloudexus\Core\DatabaseConnection::get()->prepare(
                "SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END)
                 FROM stock_movements WHERE product_id = :product_id AND warehouse_id = :warehouse_id"
            );
            $stmt->execute(['product_id' => $productId, 'warehouse_id' => $warehouseId]);
            $available = (float) ($stmt->fetchColumn() ?: 0);

            if ($quantity > $available) {
                $this->flashError(sprintf('Nincs elég készlet: elérhető %s db, kért %s db.', $available, $quantity));
                $this->redirect($redirectPath);
            }
        }

        $this->movements->create([
            'warehouse_id' => $warehouseId,
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
