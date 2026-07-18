<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\ProductModel;

class DashboardController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        $this->activeMenu = 'dashboard';
        $this->pageTitle = 'Vezérlőpult';
        $this->render('dashboard.twig', [
            'product_count' => (new ProductModel())->count(),
        ]);
    }
}
