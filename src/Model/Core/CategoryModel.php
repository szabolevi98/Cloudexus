<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class CategoryModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT c.*, p.name AS parent_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             ORDER BY c.name ASC'
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO categories (name, parent_id, created_at) VALUES (:name, :parent_id, NOW())'
        );
        $stmt->execute([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?: null,
        ]);

        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'UPDATE categories SET name = :name, parent_id = :parent_id WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'parent_id' => ($data['parent_id'] && (int) $data['parent_id'] !== $id) ? $data['parent_id'] : null,
        ]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $id]);
    }
}
