<?php

namespace Cloudexus\Core;

/**
 * Tiny translation helper. Loads per-domain PHP files from
 * src/Language/<locale>/<domain>.php (each returns a nested array) and looks
 * up dotted keys like "products.new_product" or "common.save".
 *
 * Missing keys fall back to the default locale, then to the raw key itself
 * (so gaps are visible in the UI rather than blowing up).
 */
class Lang
{
    private static string $locale = 'hu';
    private static string $fallback = 'hu';
    private static array $available = ['hu'];
    private static string $dir = '';
    private static array $cache = [];

    public static function init(string $default, array $available, ?string $requested = null): void
    {
        self::$dir = dirname(__DIR__) . '/Language';
        self::$available = $available ?: ['hu'];
        self::$fallback = $default;
        self::$locale = ($requested !== null && in_array($requested, self::$available, true))
            ? $requested
            : (in_array($default, self::$available, true) ? $default : self::$available[0]);
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function available(): array
    {
        return self::$available;
    }

    /** Translate a dotted key, optionally replacing {placeholders}. */
    public static function get(string $key, array $replace = []): string
    {
        [$domain, $path] = array_pad(explode('.', $key, 2), 2, '');
        if ($path === '') {
            return $key;
        }

        $value = self::dig(self::load(self::$locale, $domain), $path);
        if ($value === null && self::$locale !== self::$fallback) {
            $value = self::dig(self::load(self::$fallback, $domain), $path);
        }
        if ($value === null) {
            return $key;
        }

        foreach ($replace as $k => $v) {
            $value = str_replace('{' . $k . '}', (string) $v, $value);
        }

        return $value;
    }

    private static function load(string $locale, string $domain): array
    {
        $cacheKey = $locale . '.' . $domain;
        if (!array_key_exists($cacheKey, self::$cache)) {
            $file = self::$dir . '/' . $locale . '/' . $domain . '.php';
            self::$cache[$cacheKey] = is_file($file) ? (require $file) : [];
        }
        return self::$cache[$cacheKey];
    }

    private static function dig(array $data, string $path): ?string
    {
        $current = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return is_string($current) ? $current : null;
    }
}
