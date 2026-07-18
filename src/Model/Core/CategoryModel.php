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

    /**
     * Filters: q (name). Includes parent name and product count per category.
     *
     * Rows are ordered by their full breadcrumb path, so parents come in
     * alphabetical order and each parent's children follow alphabetically
     * (e.g. "Bútor", "Bútor > Irodabútor", "Bútor > Otthon", "Elektronika", …).
     * Sorting/paging is done in PHP so arbitrary nesting depths order correctly.
     */
    public function paginate(array $filters, \Cloudexus\Core\Paginator $pager): array
    {
        $where = '';
        $params = [];

        if ($filters['q'] !== '') {
            $where = 'WHERE c.name LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT c.*, p.name AS parent_name,
                    (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id) AS product_count
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             $where"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $paths = $this->paths();
        $collator = class_exists('Collator') ? new \Collator('hu_HU') : null;
        foreach ($rows as &$row) {
            $path = $paths[$row['id']] ?? $row['name'];
            $row['sort_path'] = $path;
            // Accent-folded key so Hungarian letters sort naturally without intl.
            $row['sort_key'] = strtr(mb_strtolower($path, 'UTF-8'), [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
                'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
            ]);
        }
        unset($row);

        usort($rows, function ($a, $b) use ($collator) {
            return $collator
                ? $collator->compare($a['sort_path'], $b['sort_path'])
                : strcmp($a['sort_key'], $b['sort_key']);
        });

        $pager->total = count($rows);
        $pager->clamp();

        return array_slice($rows, $pager->offset(), $pager->perPage);
    }

    /**
     * Full breadcrumb path for every category, e.g. "Szülő > Gyerek > Unoka",
     * keyed by category id. Used to show the hierarchy in lists and selects.
     */
    public function paths(): array
    {
        $rows = DatabaseConnection::get()->query('SELECT id, name, parent_id FROM categories')->fetchAll();

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }

        $paths = [];
        foreach ($byId as $id => $row) {
            $parts = [];
            $current = $row;
            $guard = 0;
            while ($current && $guard++ < 50) {
                array_unshift($parts, $current['name']);
                $current = $current['parent_id'] ? ($byId[$current['parent_id']] ?? null) : null;
            }
            $paths[$id] = implode(' > ', $parts);
        }

        return $paths;
    }

    /** Select2 AJAX search: categories by name, text = full breadcrumb path. */
    public function search(string $q, int $page = 1, int $perPage = 20): array
    {
        $paths = $this->paths();
        $offset = max(0, ($page - 1) * $perPage);
        $like = '%' . mb_strtolower($q) . '%';

        $matches = [];
        foreach ($paths as $id => $path) {
            if ($q === '' || str_contains(mb_strtolower($path), trim($like, '%'))) {
                $matches[] = ['id' => (int) $id, 'text' => $path];
            }
        }
        usort($matches, fn($a, $b) => strcmp($a['text'], $b['text']));

        $slice = array_slice($matches, $offset, $perPage);
        return [
            'results' => array_values($slice),
            'more' => count($matches) > $offset + $perPage,
        ];
    }

    public function labelsForIds(array $ids): array
    {
        $paths = $this->paths();
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if (isset($paths[$id])) {
                $out[] = ['id' => $id, 'text' => $paths[$id]];
            }
        }
        return $out;
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
