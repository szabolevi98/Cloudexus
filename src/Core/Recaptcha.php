<?php

namespace Cloudexus\Core;

/**
 * Google reCAPTCHA v3 (score alapú, nincs interaktív kihívás) ellenőrzés.
 */
class Recaptcha
{
    public static function siteKey(): string
    {
        return (string) Config::get('recaptcha.site_key', '');
    }

    public static function enabled(): bool
    {
        if (!Config::get('recaptcha.enabled', false)) {
            return false;
        }

        return self::siteKey() !== '' && (string) Config::get('recaptcha.secret_key', '') !== '';
    }

    /** Ellenőrzi a kliens által beküldött tokent a Google siteverify API-n, és a score-t az elvárt küszöbhöz méri. */
    public static function verify(string $token, string $action): bool
    {
        if (!self::enabled()) {
            return true;
        }

        if ($token === '') {
            return false;
        }

        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => Config::get('recaptcha.secret_key', ''),
                'response' => $token,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::error('reCAPTCHA siteverify hívás sikertelen.');
            return false;
        }

        $result = json_decode($response, true);
        if (!($result['success'] ?? false)) {
            return false;
        }
        if (($result['action'] ?? '') !== $action) {
            return false;
        }

        $minScore = (float) Config::get('recaptcha.min_score', 0.5);
        return (float) ($result['score'] ?? 0) >= $minScore;
    }
}
