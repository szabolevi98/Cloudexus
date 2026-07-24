<?php

namespace Cloudexus\Controller;

use Cloudexus\Core\Config;

/**
 * Switches the UI language by storing the choice in a long-lived cookie
 * (persists across sessions and login state), then redirects back.
 */
class LocaleController
{
    public function switch(string $code): void
    {
        $available = array_map('trim', explode(',', (string) Config::get('app.available_locales', 'hu,en')));

        if (in_array($code, $available, true)) {
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
