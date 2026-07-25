<?php

return [
    // List
    'page_title' => 'Stocktaking',
    'list_title' => 'Stocktakings',
    'new' => 'New stocktaking',
    'search_placeholder' => 'Stocktaking number…',
    'col_number' => 'Number',
    'col_items' => 'Items',
    'col_variances' => 'Variances',
    'variance_count' => '{count} variances',
    'accurate' => 'accurate',
    'none_found' => 'No stocktakings match the filter.',

    // Form
    'choose_warehouse' => 'Choose a warehouse…',
    'load_sheet' => 'Load count sheet',
    'note_placeholder' => 'e.g. Year-end stocktaking',
    'sheet_hint' => 'Enter the quantity you actually counted. Rows left empty are skipped. Variances are posted by the system as stock corrections.',
    'col_name' => 'Name',
    'col_book_quantity' => 'Book quantity',
    'col_counted_quantity' => 'Counted',
    'col_variance' => 'Variance',
    'submit' => 'Post stocktaking',
    'no_products' => 'There are no active products to count in this warehouse.',

    // Show
    'show_title' => 'Stocktaking: {number}',
    'variance_corrected' => '{count} variances corrected',
    'no_variance' => 'There was no variance',

    // Flash / validation
    'warehouse_and_sheet_required' => 'Choose a warehouse and fill in the count sheet.',
    'counted_quantity_required' => 'Enter a counted quantity for at least one product.',
    'booked' => 'Stocktaking recorded; variances posted as stock corrections.',
];
