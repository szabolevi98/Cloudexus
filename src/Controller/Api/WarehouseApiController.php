<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\WarehouseModel;

class WarehouseApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $filters = $this->baseFilters() + ['status' => $_GET['status'] ?? ''];
        $pager = $this->paginator();
        $rows = (new WarehouseModel())->paginate($filters, $pager);

        $this->collection($rows, $pager);
    }
}
