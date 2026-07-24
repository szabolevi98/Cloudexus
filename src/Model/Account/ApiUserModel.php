<?php

namespace Cloudexus\Model\Account;

use Cloudexus\Core\DatabaseConnection;

class ApiUserModel
{
    public function all(): array
    {
        return DatabaseConnection::get()
            ->query('SELECT * FROM api_users ORDER BY name ASC')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM api_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Resolves an active API user from its bearer token, or null. */
    public function findActiveByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT * FROM api_users WHERE token = :token AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function create(string $name): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO api_users (name, token, is_active, created_at) VALUES (:name, :token, 1, NOW())'
        );
        $stmt->execute(['name' => $name, 'token' => self::generateToken()]);
        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        DatabaseConnection::get()
            ->prepare('UPDATE api_users SET name = :name WHERE id = :id')
            ->execute(['id' => $id, 'name' => $name]);
    }

    public function setActive(int $id, bool $active): void
    {
        DatabaseConnection::get()
            ->prepare('UPDATE api_users SET is_active = :a WHERE id = :id')
            ->execute(['id' => $id, 'a' => $active ? 1 : 0]);
    }

    public function regenerateToken(int $id): void
    {
        DatabaseConnection::get()
            ->prepare('UPDATE api_users SET token = :token WHERE id = :id')
            ->execute(['id' => $id, 'token' => self::generateToken()]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM api_users WHERE id = :id')->execute(['id' => $id]);
    }
}
