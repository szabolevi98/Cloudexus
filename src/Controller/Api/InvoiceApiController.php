<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Sales\InvoiceModel;

class InvoiceApiController extends ApiController
{
    public function index(): void
    {
        $this->authenticate();

        $filters = $this->baseFilters() + [
            'partner_id' => (int) ($_GET['partner_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $pager = $this->paginator();
        $rows = (new InvoiceModel())->paginate($filters, $pager);

        $this->collection($rows, $pager);
    }

    public function show(int $id): void
    {
        $this->authenticate();

        $invoice = (new InvoiceModel())->findById($id);
        if (!$invoice) {
            $this->error('Invoice not found.', 404);
        }
        $this->json(['data' => $invoice]);
    }
}
