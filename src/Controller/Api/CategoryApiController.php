<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\CategoryModel;

class CategoryApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $pager = $this->paginator();
        $rows = (new CategoryModel())->paginate($this->baseFilters(), $pager);

        $this->collection($rows, $pager);
    }

    public function show(int $id): void
    {
        $this->authenticate();

        $category = (new CategoryModel())->findById($id);
        if (!$category) {
            $this->error('Category not found.', 404);
        }
        $this->json(['data' => $category]);
    }
}
