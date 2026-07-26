<?php

namespace Cloudexus\Core;

/**
 * SQL-töredékek a fordítható szövegek feloldásához.
 *
 * Minden fordítható szöveg egy *_description táblában van, nyelvenként egy
 * sorral. Egy lekérdezés két joint kap: a választott nyelvet és az alapnyelvet,
 * és a szöveget COALESCE-szal veszi — így egy le nem fordított rekord az
 * alapnyelvi nevét mutatja, nem pedig üresen jelenik meg.
 *
 * Használat (ProductModel):
 *
 *     Translation::join('product_description', 'product_id', 'p.id', 'pd')
 *     // -> LEFT JOIN product_description pd  ON pd.product_id  = p.id AND pd.language_id = 1
 *     //    LEFT JOIN product_description pd0 ON pd0.product_id = p.id AND pd0.language_id = 1
 *
 *     Translation::pick('pd', 'name')
 *     // -> COALESCE(NULLIF(pd.name, ''), pd0.name)
 *
 * A nyelv azonosítója egész számként kerül a lekérdezésbe (nem paraméterként),
 * mert így egyetlen execute() hívást sem kell átírni: a fragmentumok bárhol
 * beilleszthetők. Az érték a languages táblából jön és (int)-re kényszerített,
 * tehát nem kerülhet bele felhasználói adat.
 */
final class Translation
{
    /** Az alapnyelvi join aliasa a megadott alias + "0". */
    public static function alias(string $alias): string
    {
        return $alias . '0';
    }

    /**
     * A két LEFT JOIN a választott és az alapnyelvre.
     *
     * @param string $table  a description tábla neve, pl. product_description
     * @param string $fk     a hivatkozó oszlop a description táblában, pl. product_id
     * @param string $ref    a hivatkozott kifejezés, pl. p.id
     * @param string $alias  a választott nyelv aliasa, pl. pd
     */
    public static function join(string $table, string $fk, string $ref, string $alias): string
    {
        $lang = Language::id();
        $default = Language::defaultId();
        $base = self::alias($alias);

        // Az alapnyelvi join akkor is kimegy, ha a választott nyelv maga az
        // alapnyelv: így a pick() által hivatkozott alias mindig létezik, és a
        // lekérdezés alakja nem függ az éppen aktív nyelvtől.
        return "LEFT JOIN $table $alias ON $alias.$fk = $ref AND $alias.language_id = $lang"
            . "\n             LEFT JOIN $table $base ON $base.$fk = $ref AND $base.language_id = $default";
    }

    /** A szöveg a választott nyelven, alapnyelvi visszaeséssel. */
    public static function pick(string $alias, string $column): string
    {
        $base = self::alias($alias);

        return "COALESCE(NULLIF($alias.$column, ''), $base.$column)";
    }

    /** A pick() eredménye aliasszal, SELECT-listához. */
    public static function select(string $alias, string $column, ?string $as = null): string
    {
        return self::pick($alias, $column) . ' AS ' . ($as ?? $column);
    }
}
