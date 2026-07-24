<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\ParameterNameModel;

class ParameterNameApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $pager = $this->paginator();
        $rows = (new ParameterNameModel())->paginate($this->baseFilters(), $pager);

        $this->collection($rows, $pager);
    }
}
