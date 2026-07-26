<?php

namespace Cloudexus\Core;

use Cloudexus\Model\Core\LanguageModel;
use Cloudexus\Model\Core\SettingModel;
use Throwable;

/**
 * Adatnyelvek: melyik nyelven kérjük a fordítható törzsadatokat.
 *
 * A languages tábla egyetlen forrás a felület és az adatok nyelvéhez is: a
 * topbar nyelvváltója innen töltődik, a választást a cx_locale süti tartja. Ha
 * egy nyelvhez nincs felületi fordításfájl (src/Language/<kód>/), a felirat az
 * alapnyelvre esik vissza — az adat viszont már a választott nyelven jön.
 *
 * A fordítás nélküli rekordok az alapnyelvet mutatják, ezért minden lekérdezés
 * két nyelvet kap: a választottat és az alapnyelvet (lásd Translation).
 *
 * Az adatokat első használatkor tölti be, és minden hibát elnyel: friss
 * telepítésen a migráció előtt sem dőlhet el tőle az oldal.
 */
class Language
{
    /** Használt érték, ha a languages tábla még nem létezik. */
    private const FALLBACK = ['id' => 0, 'code' => 'hu', 'name' => 'Magyar'];

    private static ?array $all = null;
    private static ?array $default = null;
    private static ?array $current = null;
    private static ?string $requested = null;

    /**
     * A kért nyelv kódja (süti vagy API-paraméter). Csak eltárolja; a tényleges
     * feloldás első használatkor történik.
     */
    public static function init(?string $requestedCode): void
    {
        self::$requested = $requestedCode !== null && $requestedCode !== '' ? $requestedCode : null;
        self::$current = null;
    }

    /** Az aktuális nyelv sora (id, code, name). */
    public static function current(): array
    {
        if (self::$current === null) {
            $byCode = [];
            foreach (self::all() as $row) {
                $byCode[$row['code']] = $row;
            }

            self::$current = (self::$requested !== null && isset($byCode[self::$requested]))
                ? $byCode[self::$requested]
                : self::default();
        }

        return self::$current;
    }

    public static function id(): int
    {
        return (int) self::current()['id'];
    }

    public static function code(): string
    {
        return (string) self::current()['code'];
    }

    /** Az alapnyelv sora — erre esik vissza a hiányzó fordítás. */
    public static function default(): array
    {
        if (self::$default === null) {
            self::$default = self::loadDefault();
        }

        return self::$default;
    }

    public static function defaultId(): int
    {
        return (int) self::default()['id'];
    }

    public static function defaultCode(): string
    {
        return (string) self::default()['code'];
    }

    /** Az aktív nyelvek, sorrendben. */
    public static function all(): array
    {
        if (self::$all === null) {
            try {
                self::$all = (new LanguageModel())->activeList();
            } catch (Throwable $e) {
                Logger::error('Nyelvek betöltése sikertelen: ' . $e->getMessage());
                self::$all = [];
            }

            if (!self::$all) {
                self::$all = [self::FALLBACK];
            }
        }

        return self::$all;
    }

    /** Csak a kódok — a felület fordítója (Lang) ezt kapja meg. */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    /** Újratöltésre kényszerít, ha a nyelvek menet közben módosultak. */
    public static function reset(): void
    {
        self::$all = null;
        self::$default = null;
        self::$current = null;
    }

    private static function loadDefault(): array
    {
        $languages = self::all();

        try {
            $code = (string) (new SettingModel())->get('language.default', self::FALLBACK['code']);
            foreach ($languages as $row) {
                if ($row['code'] === $code) {
                    return $row;
                }
            }
        } catch (Throwable $e) {
            Logger::error('Alapnyelv betöltése sikertelen: ' . $e->getMessage());
        }

        // Nincs beállítás vagy nem aktív a beállított nyelv: az első aktív nyelv.
        return $languages[0];
    }
}
