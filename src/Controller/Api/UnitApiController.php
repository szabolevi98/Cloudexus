<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\UnitModel;

class UnitApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $pager = $this->paginator();
        $rows = (new UnitModel())->paginate($this->baseFilters(), $pager);

        $this->collection($rows, $pager);
    }
}
