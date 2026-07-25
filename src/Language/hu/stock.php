<?php

return [
    // Shared movement form / list bits
    'location' => 'Tárhely',
    'col_product_name' => 'Megnevezés',
    'choose_warehouse' => 'Válassz raktárat…',
    'no_location' => '— tárhely nélkül —',
    'product_search_placeholder' => 'Cikkszám vagy név…',
    'direction' => 'Irány',
    'direction_in' => 'Bevét',
    'direction_out' => 'Kiadás',
    'movement_required' => 'Raktár, termék és pozitív mennyiség megadása kötelező.',
    'not_enough' => 'Nincs elég készlet: elérhető {available} db, kért {requested} db.',

    // Overview
    'overview_title' => 'Raktárkészlet',
    'overview_product_search' => 'Termék keresés',
    'overview_search_placeholder' => 'Cikkszám vagy megnevezés…',
    'overview_none_found' => 'Nincs a szűrésnek megfelelő készlet.',

    // Stock in
    'in_title' => 'Raktári bevét',
    'in_new' => 'Új bevét',
    'in_list_title' => 'Bevétek',
    'in_submit' => 'Bevét rögzítése',
    'in_none_found' => 'Nincs a szűrésnek megfelelő bevét.',
    'in_created' => 'Raktári bevét rögzítve.',

    // Stock out
    'out_title' => 'Raktári kiadás',
    'out_new' => 'Új kiadás',
    'out_list_title' => 'Kiadások',
    'out_submit' => 'Kiadás rögzítése',
    'out_none_found' => 'Nincs a szűrésnek megfelelő kiadás.',
    'out_created' => 'Raktári kiadás rögzítve.',

    // Transfer between warehouses
    'transfer_title' => 'Raktárközi átadás',
    'transfer_new' => 'Új átadás',
    'transfer_recent' => 'Legutóbbi átadások',
    'transfer_from_warehouse' => 'Forrásraktár',
    'transfer_from_location' => 'Forrás tárhely',
    'transfer_to_warehouse' => 'Célraktár',
    'transfer_to_location' => 'Cél tárhely',
    'transfer_submit' => 'Átadás rögzítése',
    'transfer_none_found' => 'Nincs a szűrésnek megfelelő raktárközi átadás.',
    'transfer_required' => 'Forrás- és célraktár, termék és pozitív mennyiség megadása kötelező.',
    'transfer_same_warehouse' => 'A forrás- és célraktár nem lehet azonos.',
    'transfer_not_enough' => 'Nincs elég készlet a forrásraktárban: elérhető {available}, kért {requested}.',
    'transfer_created' => 'Raktárközi átadás rögzítve.',

    // Barcode collector
    'barcode_title' => 'Vonalkód gyűjtő',
    'barcode_subtitle' => 'Olvasd be a tételeket, majd könyveld egyben',
    'barcode_scan_label' => 'Vonalkód / cikkszám',
    'barcode_scan_placeholder' => 'Olvass be egy kódot, majd Enter…',
    'barcode_scan_hint' => 'Minden beolvasás +1 mennyiséget ad a tételhez.',
    'barcode_empty' => 'Még nincs beolvasott tétel.',
    'barcode_submit' => 'Tételek könyvelése',
    'barcode_unknown_code' => 'Ismeretlen kód:',
    'barcode_lookup_error' => 'Hálózati hiba a kereséskor.',
    'barcode_required' => 'Raktár és legalább egy beolvasott tétel szükséges.',
    'barcode_not_enough' => 'Nincs elég készlet: {sku} (elérhető {available}, kért {requested}).',
    'barcode_booked' => '{count} tétel {direction} könyvelve a vonalkód gyűjtőből.',
    'barcode_booked_as_in' => 'bevétként',
    'barcode_booked_as_out' => 'kiadásként',
];
