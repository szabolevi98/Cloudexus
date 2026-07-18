<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class ProductModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.name ASC'
        )->fetchAll();
    }

    public function count(): int
    {
        return (int) DatabaseConnection::get()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE sku = :sku';
        $params = ['sku' => $sku];

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
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO products (sku, name, category_id, unit, price, is_active, created_at)
             VALUES (:sku, :name, :category_id, :unit, :price, :is_active, NOW())'
        );
        $stmt->execute([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'category_id' => $data['category_id'] ?: null,
            'unit' => $data['unit'],
            'price' => $data['price'],
            'is_active' => $data['is_active'],
        ]);

        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'UPDATE products SET sku = :sku, name = :name, category_id = :category_id,
                unit = :unit, price = :price, is_active = :is_active, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'category_id' => $data['category_id'] ?: null,
            'unit' => $data['unit'],
            'price' => $data['price'],
            'is_active' => $data['is_active'],
        ]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
    }
}
