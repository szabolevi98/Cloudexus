<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class ProductModel
{
    /** All product columns written by create()/update(). */
    private const FIELDS = [
        'sku', 'barcode', 'name', 'short_description', 'description',
        'category_id', 'unit', 'price', 'vat_rate', 'min_stock',
        'width_mm', 'height_mm', 'depth_mm', 'weight_g',
        'is_active', 'is_webshop',
    ];

    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.name ASC'
        )->fetchAll();
    }

    /** Lightweight active list for related/substitute pickers. */
    public function activeSelectList(): array
    {
        return DatabaseConnection::get()
            ->query('SELECT id, sku, name FROM products WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    /** Select2 AJAX search: active products by sku/name/barcode, paginated. */
    public function search(string $q, int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $like = '%' . $q . '%';

        $stmt = DatabaseConnection::get()->prepare(
            'SELECT id, sku, name FROM products
             WHERE is_active = 1 AND (sku LIKE :q1 OR name LIKE :q2 OR barcode LIKE :q3)
             ORDER BY name ASC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue('q1', $like);
        $stmt->bindValue('q2', $like);
        $stmt->bindValue('q3', $like);
        $stmt->bindValue('lim', $perPage + 1, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $more = count($rows) > $perPage;
        $rows = array_slice($rows, 0, $perPage);

        return [
            'results' => array_map(fn($r) => ['id' => (int) $r['id'], 'text' => $r['sku'] . ' — ' . $r['name']], $rows),
            'more' => $more,
        ];
    }

    /** Resolves ids to {id,text} pairs, to preselect Select2 options on edit. */
    public function labelsForIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $rows = DatabaseConnection::get()->query(
            "SELECT id, sku, name FROM products WHERE id IN ($in)"
        )->fetchAll();

        return array_map(fn($r) => ['id' => (int) $r['id'], 'text' => $r['sku'] . ' — ' . $r['name']], $rows);
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

    /** Product with all related data loaded (images, attributes, categories, links). */
    public function findFull(int $id): ?array
    {
        $product = $this->findById($id);
        if (!$product) {
            return null;
        }

        $product['images'] = $this->images($id);
        $product['attributes'] = $this->attributes($id);
        $product['category_ids'] = $this->categoryIds($id);
        $product['related_ids'] = $this->linkedIds($id, 'related');
        $product['substitute_ids'] = $this->linkedIds($id, 'substitute');
        $product['stock_qty'] = $this->currentStock($id);

        return $product;
    }

    public function images(int $productId): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC, id ASC'
        );
        $stmt->execute(['id' => $productId]);
        return $stmt->fetchAll();
    }

    public function attributes(int $productId): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT * FROM product_attributes WHERE product_id = :id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['id' => $productId]);
        return $stmt->fetchAll();
    }

    public function categoryIds(int $productId): array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT category_id FROM product_categories WHERE product_id = :id');
        $stmt->execute(['id' => $productId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function linkedIds(int $productId, string $type): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT linked_product_id FROM product_links WHERE product_id = :id AND link_type = :t'
        );
        $stmt->execute(['id' => $productId, 't' => $type]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function currentStock(int $productId): float
    {
        $stmt = DatabaseConnection::get()->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0)
             FROM stock_movements WHERE product_id = :id"
        );
        $stmt->execute(['id' => $productId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Filtered, paginated product list with the current total stock joined in.
     * Filters: q (sku/name/barcode), category_id, status, webshop.
     */
    public function paginate(array $filters, \Cloudexus\Core\Paginator $pager): array
    {
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = '(p.sku LIKE :q1 OR p.name LIKE :q2 OR p.barcode LIKE :q3)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $where[] = '(p.category_id = :category_id OR EXISTS (
                SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = :category_id2
            ))';
            $params['category_id'] = (int) $filters['category_id'];
            $params['category_id2'] = (int) $filters['category_id'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'p.is_active = :is_active';
            $params['is_active'] = $filters['status'] === 'active' ? 1 : 0;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM products p $whereSql");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT p.*, c.name AS category_name, COALESCE(s.qty, 0) AS stock_qty,
                    (SELECT path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS thumb
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN (
                 SELECT product_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS qty
                 FROM stock_movements GROUP BY product_id
             ) s ON s.product_id = p.id
             $whereSql
             ORDER BY p.name ASC
             LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
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
        $columns = implode(', ', self::FIELDS);
        $placeholders = ':' . implode(', :', self::FIELDS);

        $stmt = DatabaseConnection::get()->prepare(
            "INSERT INTO products ($columns, created_at) VALUES ($placeholders, NOW())"
        );
        $stmt->execute($this->bind($data));

        $id = (int) DatabaseConnection::get()->lastInsertId();
        $this->syncRelations($id, $data);

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($f) => "$f = :$f", self::FIELDS));

        $stmt = DatabaseConnection::get()->prepare(
            "UPDATE products SET $sets, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute($this->bind($data) + ['id' => $id]);

        $this->syncRelations($id, $data);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
    }

    /** Looks up an active product by scanned barcode or SKU (for the vonalkód gyűjtő). */
    public function findByCode(string $code): ?array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT * FROM products WHERE (barcode = :c1 OR sku = :c2) AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['c1' => $code, 'c2' => $code]);

        return $stmt->fetch() ?: null;
    }

    /** Products whose total stock has fallen below their minimum stock level. */
    public function lowStock(int $limit = 10): array
    {
        return DatabaseConnection::get()->query(
            "SELECT p.id, p.sku, p.name, p.unit, p.min_stock, COALESCE(s.qty, 0) AS stock_qty
             FROM products p
             LEFT JOIN (
                 SELECT product_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS qty
                 FROM stock_movements GROUP BY product_id
             ) s ON s.product_id = p.id
             WHERE p.is_active = 1 AND p.min_stock > 0 AND COALESCE(s.qty, 0) < p.min_stock
             ORDER BY (COALESCE(s.qty, 0) / p.min_stock) ASC
             LIMIT " . (int) $limit
        )->fetchAll();
    }

    // --- image helpers -----------------------------------------------------

    public function addImage(int $productId, string $path, bool $isPrimary = false): void
    {
        DatabaseConnection::get()->prepare(
            'INSERT INTO product_images (product_id, path, is_primary, created_at) VALUES (:p, :path, :primary, NOW())'
        )->execute(['p' => $productId, 'path' => $path, 'primary' => $isPrimary ? 1 : 0]);

        $this->ensureOnePrimary($productId);
    }

    public function findImage(int $imageId): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM product_images WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $imageId]);
        return $stmt->fetch() ?: null;
    }

    public function deleteImage(int $imageId): void
    {
        $img = $this->findImage($imageId);
        DatabaseConnection::get()->prepare('DELETE FROM product_images WHERE id = :id')->execute(['id' => $imageId]);
        if ($img) {
            $this->ensureOnePrimary((int) $img['product_id']);
        }
    }

    public function setPrimaryImage(int $imageId): void
    {
        $img = $this->findImage($imageId);
        if (!$img) {
            return;
        }
        $pdo = DatabaseConnection::get();
        $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :p')
            ->execute(['p' => $img['product_id']]);
        $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = :id')->execute(['id' => $imageId]);
    }

    private function ensureOnePrimary(int $productId): void
    {
        $pdo = DatabaseConnection::get();
        $hasPrimary = (int) $pdo->query("SELECT COUNT(*) FROM product_images WHERE product_id = $productId AND is_primary = 1")->fetchColumn();
        if ($hasPrimary === 0) {
            $pdo->exec("UPDATE product_images SET is_primary = 1
                        WHERE id = (SELECT id FROM (SELECT id FROM product_images WHERE product_id = $productId ORDER BY sort_order, id LIMIT 1) t)");
        }
    }

    // --- internals ---------------------------------------------------------

    private function bind(array $data): array
    {
        return [
            'sku' => $data['sku'],
            'barcode' => ($data['barcode'] ?? '') !== '' ? $data['barcode'] : null,
            'name' => $data['name'],
            'short_description' => ($data['short_description'] ?? '') !== '' ? $data['short_description'] : null,
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'category_id' => $data['category_id'] ?: null,
            'unit' => $data['unit'],
            'price' => $data['price'],
            'vat_rate' => $data['vat_rate'] ?? 27,
            'min_stock' => $data['min_stock'] ?? 0,
            'width_mm' => ($data['width_mm'] ?? '') !== '' ? (int) $data['width_mm'] : null,
            'height_mm' => ($data['height_mm'] ?? '') !== '' ? (int) $data['height_mm'] : null,
            'depth_mm' => ($data['depth_mm'] ?? '') !== '' ? (int) $data['depth_mm'] : null,
            'weight_g' => ($data['weight_g'] ?? '') !== '' ? (int) $data['weight_g'] : null,
            'is_active' => $data['is_active'],
            'is_webshop' => $data['is_webshop'] ?? 1,
        ];
    }

    private function syncRelations(int $id, array $data): void
    {
        $pdo = DatabaseConnection::get();

        // Categories: primary (category_id) + any extra selected.
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $data['category_ids'] ?? []))));
        if ($data['category_id'] ?? null) {
            array_unshift($categoryIds, (int) $data['category_id']);
            $categoryIds = array_values(array_unique($categoryIds));
        }
        $pdo->prepare('DELETE FROM product_categories WHERE product_id = :id')->execute(['id' => $id]);
        $catStmt = $pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (:p, :c)');
        foreach ($categoryIds as $catId) {
            $catStmt->execute(['p' => $id, 'c' => $catId]);
        }

        // Attributes: parallel arrays attr_name[] / attr_value[].
        $pdo->prepare('DELETE FROM product_attributes WHERE product_id = :id')->execute(['id' => $id]);
        $names = $data['attr_name'] ?? [];
        $values = $data['attr_value'] ?? [];
        $attrStmt = $pdo->prepare(
            'INSERT INTO product_attributes (product_id, attr_name, attr_value, sort_order) VALUES (:p, :n, :v, :s)'
        );
        $sort = 0;
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            $value = trim((string) ($values[$i] ?? ''));
            if ($name !== '') {
                $attrStmt->execute(['p' => $id, 'n' => $name, 'v' => $value, 's' => $sort++]);
            }
        }

        // Related / substitute links.
        $this->syncLinks($id, 'related', $data['related_ids'] ?? []);
        $this->syncLinks($id, 'substitute', $data['substitute_ids'] ?? []);
    }

    private function syncLinks(int $id, string $type, array $linkedIds): void
    {
        $pdo = DatabaseConnection::get();
        $pdo->prepare('DELETE FROM product_links WHERE product_id = :id AND link_type = :t')
            ->execute(['id' => $id, 't' => $type]);

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO product_links (product_id, linked_product_id, link_type) VALUES (:p, :l, :t)'
        );
        foreach (array_unique(array_filter(array_map('intval', $linkedIds))) as $linkedId) {
            if ($linkedId !== $id) {
                $stmt->execute(['p' => $id, 'l' => $linkedId, 't' => $type]);
            }
        }
    }
}
