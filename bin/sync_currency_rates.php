<?php

/**
 * Updates the exchange rates of every non-primary currency from the MNB
 * (Hungarian National Bank) mid-rate feed.
 *
 * Does exactly the same as the "Fetch MNB mid-rates" button on the
 * Settings > Currencies page, so it is safe to run either way. The primary
 * currency's own rate always stays 1.
 *
 * Intended for cron. Example — every weekday at 07:10 (the MNB publishes
 * around 11:00 CET, so a second afternoon run is also reasonable):
 *
 *   10 7 * * 1-5 /usr/bin/php /var/www/cloudexus/bin/sync_currency_rates.php >> /var/log/cloudexus-currency.log 2>&1
 *
 * Exit codes: 0 = rates updated, 1 = the sync failed (details on stderr).
 *
 * Usage:
 *   php bin/sync_currency_rates.php
 *   php bin/sync_currency_rates.php --quiet   # only print on failure
 */

$args = array_slice($argv, 1);

$known = ['--quiet', '-q', '--help', '-h'];
foreach ($args as $arg) {
    if (!in_array($arg, $known, true)) {
        fwrite(STDERR, "Unknown option: $arg\nRun with --help to see the usage.\n");
        exit(1);
    }
}

if (array_intersect($args, ['--help', '-h'])) {
    $doc = file_get_contents(__FILE__);
    preg_match('#/\*\*(.*?)\*/#s', $doc, $m);
    echo trim(preg_replace('#^\s*\*[ ]?#m', '', $m[1] ?? '')) . "\n";
    exit(0);
}

$quiet = (bool) array_intersect($args, ['--quiet', '-q']);

require dirname(__DIR__) . '/vendor/autoload.php';

use Cloudexus\Core\Config;
use Cloudexus\Core\Currency;
use Cloudexus\Core\CurrencyRateSync;

Config::load(dirname(__DIR__) . '/config/config.ini');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Budapest'));

$result = CurrencyRateSync::run();

if (!$result['ok']) {
    $reasons = [
        'fetch_failed' => 'the MNB rate feed could not be reached or parsed',
        'primary_not_quoted' => 'the primary currency is not quoted by the MNB',
    ];
    $reason = $reasons[$result['error'] ?? ''] ?? ($result['error'] ?? 'unknown error');

    fwrite(STDERR, "Currency rate sync failed: $reason.\nSee var/log for details.\n");
    exit(1);
}

if (!$quiet) {
    $updated = $result['updated'] ?? [];
    $missing = $result['missing'] ?? [];

    echo 'MNB rate date: ' . ($result['date'] ?: 'unknown') . "\n";
    echo 'Primary currency: ' . Currency::code() . " (always 1)\n";
    echo count($updated) . ' rate(s) updated' . ($updated ? ': ' . implode(', ', $updated) : '') . "\n";

    if ($missing) {
        echo count($missing) . ' currency/currencies not quoted by the MNB, left unchanged: '
            . implode(', ', $missing) . "\n";
    }
}
