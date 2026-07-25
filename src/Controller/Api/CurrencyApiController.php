<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Core\Currency;
use Cloudexus\Model\Core\CurrencyModel;

class CurrencyApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $pager = $this->paginator();
        $rows = (new CurrencyModel())->paginate($this->baseFilters(), $pager);

        $primaryCode = Currency::code();
        foreach ($rows as &$row) {
            $row['value'] = (float) $row['value'];
            $row['is_primary'] = strtoupper((string) $row['code']) === $primaryCode;
        }
        unset($row);

        $this->collection($rows, $pager);
    }
}
