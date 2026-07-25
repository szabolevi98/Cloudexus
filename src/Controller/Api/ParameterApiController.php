<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\ParameterModel;

class ParameterApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $pager = $this->paginator();
        $rows = (new ParameterModel())->paginate($this->baseFilters(), $pager);

        $this->collection($rows, $pager);
    }
}
