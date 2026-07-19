<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class CustomerGroupModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            "SELECT g.*, (SELECT COUNT(*) FROM partners p WHERE p.customer_group_id = g.id) AS partner_count
             FROM customer_groups g ORDER BY g.name ASC"
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM customer_groups WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM customer_groups WHERE name = :name';
        $params = ['name' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = DatabaseConnection::get()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        DatabaseConnection::get()->prepare(
            'INSERT INTO customer_groups (name, description, created_at) VALUES (:name, :description, NOW())'
        )->execute(['name' => $data['name'], 'description' => $data['description'] ?: null]);

        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        DatabaseConnection::get()->prepare(
            'UPDATE customer_groups SET name = :name, description = :description WHERE id = :id'
        )->execute(['id' => $id, 'name' => $data['name'], 'description' => $data['description'] ?: null]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM customer_groups WHERE id = :id')->execute(['id' => $id]);
    }
}
