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

    /** Filters: q (name). Includes parent name and product count per category. */
    public function paginate(array $filters, \Cloudexus\Core\Paginator $pager): array
    {
        $where = '';
        $params = [];

        if ($filters['q'] !== '') {
            $where = 'WHERE c.name LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM categories c $where");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT c.*, p.name AS parent_name,
                    (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id) AS product_count
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             $where
             ORDER BY c.name ASC
             LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
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
