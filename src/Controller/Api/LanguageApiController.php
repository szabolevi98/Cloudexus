<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Core\Language;
use Cloudexus\Model\Core\LanguageModel;

class LanguageApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $pager = $this->paginator();
        $rows = (new LanguageModel())->paginate($this->baseFilters(), $pager);

        $defaultCode = Language::defaultCode();
        foreach ($rows as &$row) {
            $row['is_active'] = (bool) $row['is_active'];
            $row['is_default'] = $row['code'] === $defaultCode;
        }
        unset($row);

        $this->collection($rows, $pager);
    }
}
