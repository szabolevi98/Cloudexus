<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Core\Paginator;
use Cloudexus\Model\Core\PartnerModel;
use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Core\StockMovementModel;
use Cloudexus\Model\Core\WarehouseModel;
use Cloudexus\Model\Sales\InvoiceModel;
use Cloudexus\Model\Sales\OrderModel;

class InvoiceController extends BaseController
{
    private InvoiceModel $invoices;
    private PartnerModel $partners;
    private ProductModel $products;
    private OrderModel $orders;
    private WarehouseModel $warehouses;
    private StockMovementModel $stock;

    public function __construct()
    {
        parent::__construct();
        $this->invoices = new InvoiceModel();
        $this->partners = new PartnerModel();
        $this->products = new ProductModel();
        $this->orders = new OrderModel();
        $this->warehouses = new WarehouseModel();
        $this->stock = new StockMovementModel();
        $this->activeMenu = 'invoices';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'partner_id' => (int) ($_GET['partner_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $pager = new Paginator(25);

        $this->pageTitle = 'Számlázás';
        $this->render('invoices/list.twig', [
            'invoices' => $this->invoices->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'partners' => $this->partners->customersAndBoth(),
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $fromOrder = null;
        if (!empty($_GET['order_id'])) {
            $fromOrder = $this->orders->findById((int) $_GET['order_id']);
        }

        $this->pageTitle = 'Új számla';
        $this->render('invoices/form.twig', [
            'invoice_number' => $this->invoices->nextInvoiceNumber(),
            'partners' => $this->partners->customersAndBoth(),
            'products' => $this->products->all(),
            'warehouses' => $this->warehouses->activeList(),
            'from_order' => $fromOrder,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $items = $this->collectItems();

        if (empty($_POST['partner_id']) || empty($items)) {
            $this->flashError('A partner és legalább egy tétel megadása kötelező.');
            $this->redirect('/invoices/create');
        }

        $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);

        if ($warehouseId > 0) {
            $shortages = $this->findShortages($items, $warehouseId);
            if ($shortages) {
                $this->flashError('Nincs elég készlet a kiadáshoz: ' . implode(', ', $shortages));
                $this->redirect('/invoices/create');
            }
        }

        $id = $this->invoices->create([
            'invoice_number' => $_POST['invoice_number'],
            'order_id' => $_POST['order_id'] ?: null,
            'partner_id' => (int) $_POST['partner_id'],
            'warehouse_id' => $warehouseId ?: null,
            'status' => 'unpaid',
            'issue_date' => $_POST['issue_date'] ?: date('Y-m-d'),
            'due_date' => $_POST['due_date'] ?: date('Y-m-d', strtotime('+8 days')),
            'created_by' => Auth::id(),
        ], $items);

        $this->flashSuccess('Számla kiállítva.' . ($warehouseId ? ' A tételek raktári kiadásként is könyvelve.' : ''));
        $this->redirect('/invoices/' . $id);
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $invoice = $this->invoices->findById($id);
        if (!$invoice) {
            $this->redirect('/invoices');
        }

        $this->pageTitle = 'Számla: ' . $invoice['invoice_number'];
        $this->render('invoices/show.twig', ['invoice' => $invoice]);
    }

    public function markPaid(int $id): void
    {
        $this->requireAuth();

        $this->invoices->updateStatus($id, 'paid');
        $this->flashSuccess('Számla kifizetve jelölve.');
        $this->redirect('/invoices/' . $id);
    }

    public function cancel(int $id): void
    {
        $this->requireAuth();

        $this->invoices->updateStatus($id, 'cancelled');
        $this->flashSuccess('Számla stornózva.');
        $this->redirect('/invoices/' . $id);
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->invoices->delete($id);
        $this->flashSuccess('Számla törölve.');
        $this->redirect('/invoices');
    }

    /** Returns human-readable shortage descriptions for items not coverable from the warehouse. */
    private function findShortages(array $items, int $warehouseId): array
    {
        $needed = [];
        foreach ($items as $item) {
            $needed[$item['product_id']] = ($needed[$item['product_id']] ?? 0) + $item['quantity'];
        }

        $shortages = [];
        foreach ($needed as $productId => $quantity) {
            $available = $this->stock->availableQuantity($productId, $warehouseId);
            if ($quantity > $available) {
                $product = $this->products->findById($productId);
                $shortages[] = sprintf('%s (elérhető: %s, kért: %s)', $product['sku'] ?? $productId, $available, $quantity);
            }
        }

        return $shortages;
    }

    private function collectItems(): array
    {
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unitPrices = $_POST['unit_price'] ?? [];

        $items = [];
        foreach ($productIds as $index => $productId) {
            $productId = (int) $productId;
            $quantity = (float) str_replace(',', '.', $quantities[$index] ?? '0');
            $unitPrice = (float) str_replace(',', '.', $unitPrices[$index] ?? '0');

            if ($productId > 0 && $quantity > 0) {
                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        return $items;
    }
}
