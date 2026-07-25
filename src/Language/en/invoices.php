<?php

return [
    // List
    'list_title' => 'Invoices',
    'new' => 'New invoice',
    'invoice_number' => 'Invoice number',
    'issue_date' => 'Issued',
    'due_date' => 'Due date',
    'issued_from' => 'Issued from',
    'issued_to' => 'Issued to',
    'none_found' => 'No invoices match the filter.',

    // Form
    'from_order_prefilled' => 'Line items of order {number} prefilled.',
    'choose_partner' => 'Choose a partner…',
    'issue_date_label' => 'Issue date',
    'warehouse_stockout' => 'Stock issue from warehouse',
    'warehouse_hint' => 'If set, the invoice line items are also booked as a stock issue automatically.',
    'no_auto_issue' => '— no automatic stock issue —',
    'shipping_cost' => 'Shipping cost ({currency})',
    'payment_cost' => 'Payment cost ({currency})',
    'payment_cost_hint' => '(e.g. cash on delivery)',
    'create_invoice' => 'Issue invoice',

    // Show
    'address' => 'Address',
    'product_name' => 'Name',
    'shipping_cost_row' => 'Shipping cost',
    'payment_cost_row' => 'Payment cost',
    'mark_paid' => 'Mark as paid',
    'void' => 'Void',
    'confirm_void' => 'Are you sure you want to void this invoice?',
    'title_prefix' => 'Invoice',

    // Print
    'print_doc_title' => 'INVOICE',
    'stamp_paid' => 'PAID',
    'stamp_cancelled' => 'VOIDED',
    'seller' => 'Seller',
    'buyer' => 'Buyer',
    'tax_number' => 'Tax number',
    'bank_account' => 'Bank account',
    'payment_method' => 'Payment method',
    'payment_transfer' => 'Bank transfer',
    'total_due' => 'Total due',
    'print_footer' => 'This invoice was issued by the Cloudexus business management system.',

    // Flash / validation
    'required' => 'A partner and at least one line item are required.',
    'shortage' => 'Not enough stock for the issue: {items}',
    'shortage_item' => '{sku} (available: {available}, requested: {requested})',
    'created' => 'Invoice issued.',
    'created_with_stock' => 'Invoice issued. Line items also booked as a stock issue.',
    'marked_paid' => 'Invoice marked as paid.',
    'cancelled' => 'Invoice voided.',
    'deleted' => 'Invoice deleted.',

    // CSV
    'csv' => [
        'number' => 'Invoice number',
        'partner' => 'Partner',
        'issue_date' => 'Issued',
        'due_date' => 'Due date',
        'status' => 'Status',
        'total' => 'Total',
    ],
    'csv_status' => [
        'unpaid' => 'awaiting payment',
        'paid' => 'paid',
        'cancelled' => 'voided',
    ],
];
