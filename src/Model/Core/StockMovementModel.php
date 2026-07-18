<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class StockMovementModel
{
    public function listByType(string $type, int $limit = 100): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT m.*, p.sku, p.name AS product_name, p.unit, w.name AS warehouse_name, u.full_name AS created_by_name
             FROM stock_movements m
             JOIN products p ON p.id = m.product_id
             JOIN warehouses w ON w.id = m.warehouse_id
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.type = :type
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['type' => $type]);

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

    /**
     * Current stock per warehouse/product, computed as SUM(in) - SUM(out).
     */
    public function overview(): array
    {
        return DatabaseConnection::get()->query(
            "SELECT w.id AS warehouse_id, w.name AS warehouse_name,
                    p.id AS product_id, p.sku, p.name AS product_name, p.unit,
                    SUM(CASE WHEN m.type = 'in' THEN m.quantity ELSE -m.quantity END) AS quantity
             FROM stock_movements m
             JOIN warehouses w ON w.id = m.warehouse_id
             JOIN products p ON p.id = m.product_id
             GROUP BY w.id, p.id
             HAVING quantity != 0
             ORDER BY w.name ASC, p.name ASC"
        )->fetchAll();
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
