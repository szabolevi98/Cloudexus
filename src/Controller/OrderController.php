<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Model\Core\PartnerModel;
use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Sales\OrderModel;

class OrderController extends BaseController
{
    private OrderModel $orders;
    private PartnerModel $partners;
    private ProductModel $products;

    public function __construct()
    {
        parent::__construct();
        $this->orders = new OrderModel();
        $this->partners = new PartnerModel();
        $this->products = new ProductModel();
        $this->activeMenu = 'orders';
    }

    public function list(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Vevői rendelések';
        $this->render('orders/list.twig', [
            'orders' => $this->orders->all(),
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új vevői rendelés';
        $this->render('orders/form.twig', [
            'order_number' => $this->orders->nextOrderNumber(),
            'partners' => $this->partners->customersAndBoth(),
            'products' => $this->products->all(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $items = $this->collectItems();

        if (empty($_POST['partner_id']) || empty($items)) {
            $this->flashError('A partner és legalább egy tétel megadása kötelező.');
            $this->redirect('/orders/create');
        }

        $id = $this->orders->create([
            'order_number' => $_POST['order_number'],
            'partner_id' => (int) $_POST['partner_id'],
            'status' => 'confirmed',
            'order_date' => $_POST['order_date'] ?: date('Y-m-d'),
            'created_by' => Auth::id(),
        ], $items);

        $this->flashSuccess('Vevői rendelés létrehozva.');
        $this->redirect('/orders/' . $id);
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $order = $this->orders->findById($id);
        if (!$order) {
            $this->redirect('/orders');
        }

        $this->pageTitle = 'Rendelés: ' . $order['order_number'];
        $this->render('orders/show.twig', ['order' => $order]);
    }

    public function cancel(int $id): void
    {
        $this->requireAuth();

        $this->orders->updateStatus($id, 'cancelled');
        $this->flashSuccess('Rendelés törölve (stornózva).');
        $this->redirect('/orders/' . $id);
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->orders->delete($id);
        $this->flashSuccess('Rendelés törölve.');
        $this->redirect('/orders');
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
