<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\Config;
use Cloudexus\Core\DatabaseConnection;

/**
 * Key/value application settings stored in the DB, with a fallback to the
 * config.ini [company] section so a fresh install still has sensible values.
 */
class SettingModel
{
    private static ?array $cache = null;

    private function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            $rows = DatabaseConnection::get()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        }

        return self::$cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->load();
        return $data[$key] ?? $default;
    }

    /** Company data merged from DB settings (company.*) over config.ini fallbacks. */
    public function company(): array
    {
        $configCompany = Config::get('company', []) ?: [];
        $fields = ['name', 'address', 'tax_number', 'bank_account', 'email', 'phone'];

        $company = [];
        foreach ($fields as $field) {
            $company[$field] = $this->get('company.' . $field, $configCompany[$field] ?? '');
        }

        return $company;
    }

    public function set(string $key, ?string $value): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (:k, :v, NOW())
             ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);

        self::$cache = null;
    }

    public function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }
    }
}
