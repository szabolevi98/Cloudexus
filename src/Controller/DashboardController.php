<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Purchasing\IncomingInvoiceModel;
use Cloudexus\Model\Sales\InvoiceModel;

class DashboardController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        $this->activeMenu = 'dashboard';
        $this->pageTitle = 'Vezérlőpult';
        $this->render('dashboard.twig', [
            'product_count' => (new ProductModel())->count(),
            'outstanding_total' => (new InvoiceModel())->outstandingTotal(),
            'payable_total' => (new IncomingInvoiceModel())->outstandingTotal(),
        ]);
    }
}
