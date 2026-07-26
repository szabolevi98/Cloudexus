<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;
use Cloudexus\Core\Language;
use Cloudexus\Core\Translation;

class CategoryModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT c.*, ' . Translation::select('cd', 'name') . ', ' . Translation::select('cd', 'description') . ',
                    ' . Translation::select('pcd', 'name', 'parent_name') . '
             FROM categories c
             ' . Translation::join('category_description', 'category_id', 'c.id', 'cd') . '
             LEFT JOIN categories p ON p.id = c.parent_id
             ' . Translation::join('category_description', 'category_id', 'p.id', 'pcd') . '
             ORDER BY name ASC'
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
        $conds = [];
        $params = [];

        if ($filters['q'] !== '') {
            $conds[] = Translation::pick('cd', 'name') . ' LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['updated_since'])) {
            $conds[] = 'c.updated_at >= :updated_since';
            $params['updated_since'] = $filters['updated_since'];
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        $stmt = DatabaseConnection::get()->prepare(
            'SELECT c.*, ' . Translation::select('cd', 'name') . ', ' . Translation::select('cd', 'description') . ',
                    ' . Translation::select('pcd', 'name', 'parent_name') . ',
                    (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id) AS product_count
             FROM categories c
             ' . Translation::join('category_description', 'category_id', 'c.id', 'cd') . '
             LEFT JOIN categories p ON p.id = c.parent_id
             ' . Translation::join('category_description', 'category_id', 'p.id', 'pcd') . "
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
        $rows = DatabaseConnection::get()->query(
            'SELECT c.id, ' . Translation::select('cd', 'name') . ', c.parent_id
             FROM categories c
             ' . Translation::join('category_description', 'category_id', 'c.id', 'cd')
        )->fetchAll();

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
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT c.*, ' . Translation::select('cd', 'name') . ', ' . Translation::select('cd', 'description') . '
             FROM categories c
             ' . Translation::join('category_description', 'category_id', 'c.id', 'cd') . '
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO categories (parent_id, is_active, created_at) VALUES (:parent_id, :is_active, NOW())'
        );
        $stmt->execute([
            'parent_id' => $data['parent_id'] ?: null,
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ]);

        $id = (int) DatabaseConnection::get()->lastInsertId();
        $this->saveDescriptions($id, $data);

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'UPDATE categories SET parent_id = :parent_id, is_active = :is_active WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'parent_id' => ($data['parent_id'] && (int) $data['parent_id'] !== $id) ? $data['parent_id'] : null,
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ]);

        $this->saveDescriptions($id, $data);
    }

    /**
     * A kategória fordítható szövegei nyelvenként. A $data['name'] és
     * $data['description'] nyelv szerint kulcsolt tömb (nyelv id => szöveg);
     * egyetlen string is elfogadott, az az alapnyelv sorába kerül.
     */
    public function saveDescriptions(int $categoryId, array $data): void
    {
        $names = self::perLanguage($data['name'] ?? null);
        $descriptions = self::perLanguage($data['description'] ?? null);

        $languageIds = array_unique(array_merge(array_keys($names), array_keys($descriptions)));
        if (!$languageIds) {
            return;
        }

        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO category_description (category_id, language_id, name, description)
             VALUES (:category_id, :language_id, :name, :description)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
        );

        foreach ($languageIds as $languageId) {
            $stmt->execute([
                'category_id' => $categoryId,
                'language_id' => $languageId,
                'name' => (string) ($names[$languageId] ?? ''),
                'description' => ($descriptions[$languageId] ?? '') !== '' ? $descriptions[$languageId] : null,
            ]);
        }
    }

    /**
     * Fordítható szöveg nyelv szerint kulcsolt tömbje. Sima stringet is elfogad
     * (az alapnyelvhez rendeli), így a még nem nyelvesített hívók sem törnek el.
     *
     * @return array<int, string>
     */
    private static function perLanguage(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            return [Language::defaultId() => (string) $value];
        }

        $out = [];
        foreach ($value as $languageId => $text) {
            $languageId = (int) $languageId;
            if ($languageId > 0) {
                $out[$languageId] = (string) $text;
            }
        }

        return $out;
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $id]);
    }
}
