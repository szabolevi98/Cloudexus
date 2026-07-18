<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Auth;
use Cloudexus\Model\Cash\CashVoucherModel;
use Cloudexus\Model\Core\PartnerModel;
use Cloudexus\Model\Purchasing\IncomingInvoiceModel;
use Cloudexus\Model\Sales\InvoiceModel;

class CashVoucherController extends BaseController
{
    private CashVoucherModel $vouchers;
    private PartnerModel $partners;
    private InvoiceModel $invoices;
    private IncomingInvoiceModel $incomingInvoices;

    public function __construct()
    {
        parent::__construct();
        $this->vouchers = new CashVoucherModel();
        $this->partners = new PartnerModel();
        $this->invoices = new InvoiceModel();
        $this->incomingInvoices = new IncomingInvoiceModel();
        $this->activeMenu = 'cash';
    }

    public function list(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'type' => $_GET['type'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $pager = new \Cloudexus\Core\Paginator(25);

        $this->pageTitle = 'Pénztárbizonylat';
        $this->render('cash/list.twig', [
            'vouchers' => $this->vouchers->paginate($filters, $pager),
            'pager' => $pager->toTwig($filters),
            'filters' => $filters,
            'balance' => $this->vouchers->currentBalance(),
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->pageTitle = 'Új pénztárbizonylat';
        $this->render('cash/form.twig', [
            'voucher_number' => $this->vouchers->nextVoucherNumber(),
            'partners' => $this->partners->all(),
            'unpaid_invoices' => $this->invoices->unpaidList(),
            'unpaid_incoming_invoices' => $this->incomingInvoices->unpaidList(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $amount = (float) str_replace(',', '.', $_POST['amount'] ?? '0');

        if ($amount <= 0) {
            $this->flashError('Az összeg megadása kötelező.');
            $this->redirect('/cash/create');
        }

        $this->vouchers->create([
            'voucher_number' => $_POST['voucher_number'],
            'type' => $_POST['type'] ?? 'bevetel',
            'amount' => $amount,
            'partner_id' => $_POST['partner_id'] ?? null,
            'invoice_id' => $_POST['invoice_id'] ?? null,
            'incoming_invoice_id' => $_POST['incoming_invoice_id'] ?? null,
            'note' => trim($_POST['note'] ?? ''),
            'voucher_date' => $_POST['voucher_date'] ?: date('Y-m-d'),
            'created_by' => Auth::id(),
        ]);

        $this->flashSuccess('Pénztárbizonylat rögzítve.');
        $this->redirect('/cash');
    }

    public function delete(int $id): void
    {
        $this->requireAuth();

        $this->vouchers->delete($id);
        $this->flashSuccess('Pénztárbizonylat törölve.');
        $this->redirect('/cash');
    }
}
