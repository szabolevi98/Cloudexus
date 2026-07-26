<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Config;
use Cloudexus\Core\Language;

/**
 * Switches the language by storing the choice in a long-lived cookie (persists
 * across sessions and login state), then redirects back. The same choice drives
 * both the interface texts and the language the data is read in.
 */
class LocaleController
{
    public function switch(string $code): void
    {
        if (in_array($code, Language::codes(), true)) {
            setcookie('cx_locale', $code, [
                'expires' => time() + 31536000, // ~1 year
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $base = (string) Config::get('app.base_url');
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $target = ($referer !== '' && str_starts_with($referer, $base)) ? $referer : $base . '/dashboard';

        header('Location: ' . $target);
        exit;
    }
}
