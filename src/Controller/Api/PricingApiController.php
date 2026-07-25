<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\ProductModel;

class PricingApiController extends ApiController
{
    public function effective(): void
    {
        $this->authenticate();

        $productId = (int) ($_GET['product_id'] ?? 0);
        $partnerId = (int) ($_GET['partner_id'] ?? 0) ?: null;

        if ($productId <= 0) {
            $this->error('The product_id parameter is required.', 422);
        }

        $this->resource((new ProductModel())->effectivePrice($productId, $partnerId));
    }
}
