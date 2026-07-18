<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Purchasing\IncomingInvoiceModel;
use Cloudexus\Model\Sales\InvoiceModel;
use Cloudexus\Model\Sales\OrderModel;

class DashboardController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        $dailyOrders = (new OrderModel())->dailyTotals(10);

        $this->activeMenu = 'dashboard';
        $this->pageTitle = 'Vezérlőpult';
        $this->render('dashboard.twig', [
            'product_count' => (new ProductModel())->count(),
            'outstanding_total' => (new InvoiceModel())->outstandingTotal(),
            'payable_total' => (new IncomingInvoiceModel())->outstandingTotal(),
            'daily_orders' => $dailyOrders,
            'orders_total_value' => array_sum(array_column($dailyOrders, 'total_value')),
        ]);
    }
}
