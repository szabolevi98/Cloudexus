<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\ProductModel;

/**
 * AJAX endpoint the order/invoice line-item picker calls to resolve the
 * price to prefill: vevőcsoport ár (ha van, a partnerhez tartozó csoportnak
 * van felülírt ára ehhez a termékhez) vagy a termék saját ára/akciós ára.
 */
class PricingController extends BaseController
{
    public function effective(): void
    {
        $this->requireAuth();

        $productId = (int) ($_GET['product_id'] ?? 0);
        $partnerId = (int) ($_GET['partner_id'] ?? 0) ?: null;

        if ($productId <= 0) {
            $this->json(['price' => 0, 'is_sale' => false]);
        }

        $this->json((new ProductModel())->effectivePrice($productId, $partnerId));
    }
}
