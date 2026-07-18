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

        $orders = new OrderModel();
        $invoices = new InvoiceModel();

        $dailyOrders = $orders->dailyTotals(10);
        $topCategories = $orders->topCategories(30, 6);

        $this->activeMenu = 'dashboard';
        $this->pageTitle = 'Vezérlőpult';
        $this->render('dashboard.twig', [
            'product_count' => (new ProductModel())->count(),
            'outstanding' => $invoices->outstandingBreakdown(),
            'payable' => (new IncomingInvoiceModel())->outstandingBreakdown(),
            'daily_orders' => $dailyOrders,
            'orders_total_value' => array_sum(array_column($dailyOrders, 'total_value')),
            'top_categories' => $topCategories,
            'top_categories_max' => $topCategories ? max(array_column($topCategories, 'value')) : 0,
            'recent_invoices' => $invoices->recent(6),
            'today' => date('Y-m-d'),
        ]);
    }
}
