<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\CustomerGroupModel;

class CustomerGroupApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $pager = $this->paginator();
        $rows = (new CustomerGroupModel())->paginate($this->baseFilters(), $pager);

        $this->collection($rows, $pager);
    }

    public function show(int $id): void
    {
        $this->authenticate();

        $group = (new CustomerGroupModel())->findById($id);
        if (!$group) {
            $this->error('Customer group not found.', 404);
        }
        $this->json(['data' => $group]);
    }
}
