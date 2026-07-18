<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;
use Cloudexus\Core\Paginator;

class StockMovementModel
{
    /**
     * Filtered, paginated movement list for one movement type ('in'|'out').
     * Filters: warehouse_id, q (product sku/name), date_from, date_to.
     */
    public function paginateByType(string $type, array $filters, Paginator $pager): array
    {
        $where = ['m.type = :type'];
        $params = ['type' => $type];

        if (!empty($filters['warehouse_id'])) {
            $where[] = 'm.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = (int) $filters['warehouse_id'];
        }
        if ($filters['q'] !== '') {
            $where[] = '(p.sku LIKE :q1 OR p.name LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }
        if ($filters['date_from'] !== '') {
            $where[] = 'm.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'm.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $count = DatabaseConnection::get()->prepare(
            "SELECT COUNT(*) FROM stock_movements m JOIN products p ON p.id = m.product_id $whereSql"
        );
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT m.*, p.sku, p.name AS product_name, p.unit, w.name AS warehouse_name, u.full_name AS created_by_name
             FROM stock_movements m
             JOIN products p ON p.id = m.product_id
             JOIN warehouses w ON w.id = m.warehouse_id
             LEFT JOIN users u ON u.id = m.created_by
             $whereSql
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array $data Accepts an optional 'created_at' (Y-m-d H:i:s) to backdate the movement, e.g. for seeding demo data.
     */
    public function create(array $data): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO stock_movements (warehouse_id, product_id, type, quantity, note, created_by, created_at)
             VALUES (:warehouse_id, :product_id, :type, :quantity, :note, :created_by, :created_at)'
        );
        $stmt->execute([
            'warehouse_id' => $data['warehouse_id'],
            'product_id' => $data['product_id'],
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'note' => $data['note'] ?: null,
            'created_by' => $data['created_by'] ?: null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) DatabaseConnection::get()->lastInsertId();
    }

    /** Books a warehouse-to-warehouse transfer as an out + in movement pair, atomically. */
    public function transfer(int $fromWarehouseId, int $toWarehouseId, int $productId, float $quantity, string $note, ?int $userId): void
    {
        $pdo = DatabaseConnection::get();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO stock_movements (warehouse_id, product_id, type, quantity, note, created_by, created_at)
                 VALUES (:warehouse_id, :product_id, :type, :quantity, :note, :created_by, NOW())'
            );

            foreach ([['out', $fromWarehouseId], ['in', $toWarehouseId]] as [$type, $warehouseId]) {
                $stmt->execute([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'type' => $type,
                    'quantity' => $quantity,
                    'note' => $note,
                    'created_by' => $userId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Latest transfer movement legs (identified by their note prefix). */
    public function recentTransfers(int $limit = 30): array
    {
        return DatabaseConnection::get()->query(
            "SELECT m.*, p.sku, p.name AS product_name, p.unit, w.name AS warehouse_name, u.full_name AS created_by_name
             FROM stock_movements m
             JOIN products p ON p.id = m.product_id
             JOIN warehouses w ON w.id = m.warehouse_id
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.note LIKE 'Raktárközi átadás%'
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT " . (int) $limit
        )->fetchAll();
    }

    public function availableQuantity(int $productId, int $warehouseId): float
    {
        $stmt = DatabaseConnection::get()->prepare(
            "SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END)
             FROM stock_movements WHERE product_id = :product_id AND warehouse_id = :warehouse_id"
        );
        $stmt->execute(['product_id' => $productId, 'warehouse_id' => $warehouseId]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Current stock per warehouse/product, computed as SUM(in) - SUM(out).
     * Filters: warehouse_id, q (product sku/name).
     */
    public function overview(array $filters, Paginator $pager): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['warehouse_id'])) {
            $where[] = 'm.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = (int) $filters['warehouse_id'];
        }
        if ($filters['q'] !== '') {
            $where[] = '(p.sku LIKE :q1 OR p.name LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $baseSql = "SELECT w.id AS warehouse_id, w.name AS warehouse_name,
                           p.id AS product_id, p.sku, p.name AS product_name, p.unit,
                           SUM(CASE WHEN m.type = 'in' THEN m.quantity ELSE -m.quantity END) AS quantity
                    FROM stock_movements m
                    JOIN warehouses w ON w.id = m.warehouse_id
                    JOIN products p ON p.id = m.product_id
                    $whereSql
                    GROUP BY w.id, p.id
                    HAVING quantity != 0";

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM ($baseSql) t");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "$baseSql ORDER BY warehouse_name ASC, product_name ASC LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function totalQuantityForProduct(int $productId): float
    {
        $stmt = DatabaseConnection::get()->prepare(
            "SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END)
             FROM stock_movements WHERE product_id = :product_id"
        );
        $stmt->execute(['product_id' => $productId]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }
}
