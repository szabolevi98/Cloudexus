<?php

return [
    // Shared movement form / list bits
    'location' => 'Location',
    'col_product_name' => 'Name',
    'choose_warehouse' => 'Choose a warehouse…',
    'no_location' => '— no location —',
    'product_search_placeholder' => 'SKU or name…',
    'direction' => 'Direction',
    'direction_in' => 'Stock in',
    'direction_out' => 'Stock out',
    'movement_required' => 'Warehouse, product and a positive quantity are required.',
    'not_enough' => 'Not enough stock: {available} pcs available, {requested} pcs requested.',

    // Overview
    'overview_title' => 'Stock on hand',
    'overview_product_search' => 'Product search',
    'overview_search_placeholder' => 'SKU or name…',
    'overview_none_found' => 'No stock matches the filter.',

    // Stock in
    'in_title' => 'Stock in',
    'in_new' => 'New stock in',
    'in_list_title' => 'Stock in',
    'in_submit' => 'Record stock in',
    'in_none_found' => 'No stock-in record matches the filter.',
    'in_created' => 'Stock in recorded.',

    // Stock out
    'out_title' => 'Stock out',
    'out_new' => 'New stock out',
    'out_list_title' => 'Stock out',
    'out_submit' => 'Record stock out',
    'out_none_found' => 'No stock-out record matches the filter.',
    'out_created' => 'Stock out recorded.',

    // Transfer between warehouses
    'transfer_title' => 'Warehouse transfer',
    'transfer_new' => 'New transfer',
    'transfer_recent' => 'Recent transfers',
    'transfer_from_warehouse' => 'Source warehouse',
    'transfer_from_location' => 'Source location',
    'transfer_to_warehouse' => 'Target warehouse',
    'transfer_to_location' => 'Target location',
    'transfer_submit' => 'Record transfer',
    'transfer_none_found' => 'No warehouse transfer matches the filter.',
    'transfer_required' => 'Source and target warehouse, product and a positive quantity are required.',
    'transfer_same_warehouse' => 'The source and target warehouse cannot be the same.',
    'transfer_not_enough' => 'Not enough stock in the source warehouse: {available} available, {requested} requested.',
    'transfer_created' => 'Warehouse transfer recorded.',

    // Barcode collector
    'barcode_title' => 'Barcode collector',
    'barcode_subtitle' => 'Scan the items, then book them in one go',
    'barcode_scan_label' => 'Barcode / SKU',
    'barcode_scan_placeholder' => 'Scan a code, then press Enter…',
    'barcode_scan_hint' => 'Every scan adds +1 to the item quantity.',
    'barcode_empty' => 'No item scanned yet.',
    'barcode_submit' => 'Book items',
    'barcode_unknown_code' => 'Unknown code:',
    'barcode_lookup_error' => 'Network error during lookup.',
    'barcode_required' => 'A warehouse and at least one scanned item are required.',
    'barcode_not_enough' => 'Not enough stock: {sku} ({available} available, {requested} requested).',
    'barcode_booked' => '{count} items booked as {direction} from the barcode collector.',
    'barcode_booked_as_in' => 'stock in',
    'barcode_booked_as_out' => 'stock out',
];
