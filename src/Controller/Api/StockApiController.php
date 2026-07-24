<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\StockMovementModel;

class StockApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'warehouse_id' => (int) ($_GET['warehouse_id'] ?? 0),
            'location_id' => (int) ($_GET['location_id'] ?? 0),
            'product_id' => (int) ($_GET['product_id'] ?? 0),
        ];
        $pager = $this->paginator();
        $rows = (new StockMovementModel())->overview($filters, $pager);

        $this->collection($rows, $pager);
    }
}
