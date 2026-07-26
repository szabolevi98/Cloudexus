<?php

return [
    'new' => 'Új nyelv',
    'list_title' => 'Nyelvek',
    'name' => 'Megnevezés',
    'name_placeholder' => 'pl. Deutsch',
    'name_hint' => 'A nyelv a saját nevén jelenik meg a nyelvváltóban.',
    'code' => 'Kód',
    'code_placeholder' => 'pl. de',
    'code_hint' => 'Rövid nyelvkód. A felület fordítása a src/Language/&lt;kód&gt;/ mappából jön; ha nincs ilyen mappa, a feliratok az alapnyelven maradnak, az adatok viszont már ezen a nyelven jönnek.',
    'sort_order' => 'Sorrend',
    'active' => 'Aktív',
    'inactive' => 'Inaktív',
    'status' => 'Állapot',
    'search_placeholder' => 'Kód vagy megnevezés…',
    'none_found' => 'Nincs a szűrésnek megfelelő nyelv.',

    'default' => 'Alapnyelv',
    'default_badge' => 'Alapnyelv',
    'set_default' => 'Legyen ez az alapnyelv',
    'default_explained' => 'A fordítható adatok (terméknév, leírás, kategória, mértékegység, paraméter) nyelvenként külön tárolódnak. Ha egy rekordnak nincs fordítása a választott nyelven, az alapnyelvi szövege jelenik meg — így soha nem lesz névtelen termék a listákban.',
    'documents_note' => 'A kiállított bizonylatok (rendelés, számla) a rögzítés pillanatában eltárolják a terméknevet az alapnyelven, ezért nyelvváltásra és későbbi átnevezésre sem változnak meg.',

    'translations' => 'Fordítások',
    'translation_count' => '{count} fordítás-sor',

    'name_required' => 'A megnevezés megadása kötelező.',
    'code_invalid' => 'A kód 2–5 karakteres, betűkből (és kötőjelből) álló nyelvkód legyen, pl. de vagy pt-br.',
    'code_exists' => 'Ez a nyelvkód már létezik.',
    'created' => 'Nyelv hozzáadva.',
    'updated' => 'Nyelv frissítve.',
    'deleted' => 'Nyelv törölve.',
    'cannot_delete_default' => 'Az alapnyelv nem törölhető. Előbb állíts be másikat alapnyelvnek.',
    'cannot_deactivate_default' => 'Az alapnyelv nem kapcsolható ki.',
    'delete_warning' => 'A nyelv törlésével a hozzá tartozó fordítások is törlődnek. Biztosan törlöd?',
    'default_changed' => 'Az alapnyelv mostantól: {name}.',
];
