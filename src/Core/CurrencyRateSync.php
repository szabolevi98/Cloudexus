<?php

namespace Cloudexus\Core;

use Cloudexus\Model\Core\CurrencyModel;
use Cloudexus\Model\Core\SettingModel;

/**
 * MNB közép-árfolyamok átvezetése a currencies táblába.
 *
 * Az MNB minden árfolyamot forintban ad meg, a tárolt value viszont az
 * elsődleges pénznemhez képesti váltószám (1 elsődleges egység ennyi az adott
 * pénznemben). Az átszámítás ezért a forinton keresztül történik:
 *
 *     value(C) = forint(1 elsődleges egység) / forint(1 C egység)
 *
 * Így akkor is helyes, ha az elsődleges pénznem nem a forint. Az elsődleges
 * pénznem value-ja definíció szerint 1.
 *
 * A felület gombja és a bin/sync_currency_rates.php cron szkript is ezt hívja.
 */
class CurrencyRateSync
{
    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     date?: string,
     *     updated?: list<string>,
     *     missing?: list<string>
     * }
     */
    public static function run(): array
    {
        $fetched = MnbExchangeRates::fetch();
        if ($fetched === null) {
            return ['ok' => false, 'error' => 'fetch_failed'];
        }

        $rates = $fetched['rates'];
        $currencyModel = new CurrencyModel();
        $primaryCode = Currency::code();

        // Az elsődleges pénznem árfolyama kell a forinthoz, különben nincs mihez mérni.
        $primaryInHuf = $rates[$primaryCode] ?? null;
        if ($primaryInHuf === null || $primaryInHuf <= 0) {
            Logger::error('Az elsődleges pénznem (' . $primaryCode . ') nem szerepel az MNB árfolyamai között.');
            return ['ok' => false, 'error' => 'primary_not_quoted'];
        }

        $updated = [];
        $missing = [];

        foreach ($currencyModel->all() as $currency) {
            $code = strtoupper((string) $currency['code']);

            if ($code === $primaryCode) {
                // Önmagára mindig 1, akkor is, ha korábban elállítódott.
                if ((float) $currency['value'] !== 1.0) {
                    $currencyModel->updateValueByCode($code, 1.0);
                }
                continue;
            }

            $inHuf = $rates[$code] ?? null;
            if ($inHuf === null || $inHuf <= 0) {
                $missing[] = $code;
                continue;
            }

            $currencyModel->updateValueByCode($code, $primaryInHuf / $inHuf);
            $updated[] = $code;
        }

        (new SettingModel())->setMany([
            'currency.mnb_synced_at' => date('Y-m-d H:i:s'),
            'currency.mnb_rate_date' => $fetched['date'],
        ]);

        Currency::reset();

        return [
            'ok' => true,
            'date' => $fetched['date'],
            'updated' => $updated,
            'missing' => $missing,
        ];
    }
}
