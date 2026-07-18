<?php

namespace Cloudexus\Controller;

class DashboardController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        $this->render('dashboard.twig');
    }
}
