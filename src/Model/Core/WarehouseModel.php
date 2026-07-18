<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class WarehouseModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query('SELECT * FROM warehouses ORDER BY name ASC')->fetchAll();
    }

    public function activeList(): array
    {
        return DatabaseConnection::get()
            ->query('SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM warehouses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO warehouses (name, address, is_active, created_at) VALUES (:name, :address, :is_active, NOW())'
        );
        $stmt->execute([
            'name' => $data['name'],
            'address' => $data['address'] ?: null,
            'is_active' => $data['is_active'],
        ]);

        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'UPDATE warehouses SET name = :name, address = :address, is_active = :is_active WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'address' => $data['address'] ?: null,
            'is_active' => $data['is_active'],
        ]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM warehouses WHERE id = :id')->execute(['id' => $id]);
    }
}
