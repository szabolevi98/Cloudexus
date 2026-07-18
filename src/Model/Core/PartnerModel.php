<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class PartnerModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query('SELECT * FROM partners ORDER BY name ASC')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function customersAndBoth(): array
    {
        return DatabaseConnection::get()
            ->query("SELECT * FROM partners WHERE type IN ('customer', 'both') AND is_active = 1 ORDER BY name ASC")
            ->fetchAll();
    }

    public function suppliersAndBoth(): array
    {
        return DatabaseConnection::get()
            ->query("SELECT * FROM partners WHERE type IN ('supplier', 'both') AND is_active = 1 ORDER BY name ASC")
            ->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO partners (type, name, tax_number, email, phone, address, is_active, created_at)
             VALUES (:type, :name, :tax_number, :email, :phone, :address, :is_active, NOW())'
        );
        $stmt->execute([
            'type' => $data['type'],
            'name' => $data['name'],
            'tax_number' => $data['tax_number'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'is_active' => $data['is_active'],
        ]);

        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'UPDATE partners SET type = :type, name = :name, tax_number = :tax_number, email = :email,
                phone = :phone, address = :address, is_active = :is_active, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'type' => $data['type'],
            'name' => $data['name'],
            'tax_number' => $data['tax_number'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'is_active' => $data['is_active'],
        ]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM partners WHERE id = :id')->execute(['id' => $id]);
    }
}
