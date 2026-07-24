<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\ProductModel;

class ProductApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $filters = $this->baseFilters() + [
            'category_id' => (int) ($_GET['category_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
        ];
        $pager = $this->paginator();
        $rows = (new ProductModel())->paginate($filters, $pager);

        $this->collection($rows, $pager);
    }

    public function show(int $id): void
    {
        $this->authenticate();

        $product = (new ProductModel())->findFull($id);
        if (!$product) {
            $this->error('Product not found.', 404);
        }
        $this->json(['data' => $product]);
    }

    public function showBySku(string $sku): void
    {
        $this->authenticate();

        $product = (new ProductModel())->findFullBySku($sku);
        if (!$product) {
            $this->error('Product not found.', 404);
        }
        $this->json(['data' => $product]);
    }
}
