<?php

return [
    // List
    'page_title' => 'Leltározás',
    'list_title' => 'Leltárak',
    'new' => 'Új leltár',
    'search_placeholder' => 'Leltár azonosító…',
    'col_number' => 'Azonosító',
    'col_items' => 'Tételek',
    'col_variances' => 'Eltérések',
    'variance_count' => '{count} eltérés',
    'accurate' => 'pontos',
    'none_found' => 'Nincs a szűrésnek megfelelő leltár.',

    // Form
    'choose_warehouse' => 'Válassz raktárat…',
    'load_sheet' => 'Leltárív betöltése',
    'note_placeholder' => 'pl. Év végi leltár',
    'sheet_hint' => 'Írd be a ténylegesen megszámolt mennyiséget. Az üresen hagyott sorokat kihagyjuk. Az eltéréseket a rendszer készletkorrekcióként könyveli.',
    'col_name' => 'Megnevezés',
    'col_book_quantity' => 'Könyv szerint',
    'col_counted_quantity' => 'Megszámolt',
    'col_variance' => 'Eltérés',
    'submit' => 'Leltár könyvelése',
    'no_products' => 'Ebben a raktárban nincs aktív termék a leltározáshoz.',

    // Show
    'show_title' => 'Leltár: {number}',
    'variance_corrected' => '{count} eltérés korrigálva',
    'no_variance' => 'Nem volt eltérés',

    // Flash / validation
    'warehouse_and_sheet_required' => 'Válassz raktárat és töltsd ki a leltárívet.',
    'counted_quantity_required' => 'Legalább egy termékhez adj meg leltározott mennyiséget.',
    'booked' => 'Leltár rögzítve, az eltérések készletkorrekcióként könyvelve.',
];
