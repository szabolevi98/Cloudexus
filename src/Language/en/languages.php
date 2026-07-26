<?php

return [
    'new' => 'New language',
    'list_title' => 'Languages',
    'name' => 'Name',
    'name_placeholder' => 'e.g. Deutsch',
    'name_hint' => 'The language appears under its own name in the switcher.',
    'code' => 'Code',
    'code_placeholder' => 'e.g. de',
    'code_hint' => 'Short language code. The interface translation comes from src/Language/&lt;code&gt;/; without such a directory the labels stay in the default language, but the data already comes in this language.',
    'sort_order' => 'Order',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'status' => 'Status',
    'search_placeholder' => 'Code or name…',
    'none_found' => 'No languages match the filter.',

    'default' => 'Default',
    'default_badge' => 'Default',
    'set_default' => 'Make this the default',
    'default_explained' => 'Translatable data (product name, descriptions, categories, units, parameters) is stored per language. When a record has no translation in the chosen language, its default-language text is shown — so a product is never nameless in a list.',
    'documents_note' => 'Issued documents (orders, invoices) store the product name in the default language at the moment they are saved, so neither switching language nor a later rename changes them.',

    'translations' => 'Translations',
    'translation_count' => '{count} translation rows',

    'name_required' => 'The name is required.',
    'code_invalid' => 'The code must be a 2–5 character language code of letters (and a hyphen), e.g. de or pt-br.',
    'code_exists' => 'This language code already exists.',
    'created' => 'Language added.',
    'updated' => 'Language updated.',
    'deleted' => 'Language deleted.',
    'cannot_delete_default' => 'The default language cannot be deleted. Set another one as default first.',
    'cannot_deactivate_default' => 'The default language cannot be deactivated.',
    'delete_warning' => 'Deleting a language also deletes its translations. Are you sure?',
    'default_changed' => 'The default language is now {name}.',
];
