<?php

namespace Cloudexus\Controller;

class DashboardController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        $this->activeMenu = 'dashboard';
        $this->pageTitle = 'Vezérlőpult';
        $this->render('dashboard.twig');
    }
}
