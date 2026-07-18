<?php

namespace Cloudexus\Model\Sales;

use Cloudexus\Core\DatabaseConnection;

class OrderModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT o.*, p.name AS partner_name
             FROM orders o
             JOIN partners p ON p.id = o.partner_id
             ORDER BY o.created_at DESC'
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT o.*, p.name AS partner_name
             FROM orders o
             JOIN partners p ON p.id = o.partner_id
             WHERE o.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->items($id);

        return $order;
    }

    public function items(int $orderId): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT oi.*, pr.sku, pr.name AS product_name, pr.unit
             FROM order_items oi
             JOIN products pr ON pr.id = oi.product_id
             WHERE oi.order_id = :order_id'
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function nextOrderNumber(): string
    {
        $year = date('Y');
        $stmt = DatabaseConnection::get()->prepare(
            "SELECT COUNT(*) FROM orders WHERE order_number LIKE :pattern"
        );
        $stmt->execute(['pattern' => "REND-$year-%"]);
        $count = (int) $stmt->fetchColumn() + 1;

        return sprintf('REND-%s-%04d', $year, $count);
    }

    /**
     * @param array $items List of ['product_id' => int, 'quantity' => float, 'unit_price' => float]
     */
    public function create(array $data, array $items): int
    {
        $pdo = DatabaseConnection::get();
        $pdo->beginTransaction();

        try {
            $total = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));

            $stmt = $pdo->prepare(
                'INSERT INTO orders (order_number, partner_id, status, order_date, total_amount, created_by, created_at)
                 VALUES (:order_number, :partner_id, :status, :order_date, :total_amount, :created_by, NOW())'
            );
            $stmt->execute([
                'order_number' => $data['order_number'],
                'partner_id' => $data['partner_id'],
                'status' => $data['status'],
                'order_date' => $data['order_date'],
                'total_amount' => $total,
                'created_by' => $data['created_by'] ?: null,
            ]);

            $orderId = (int) $pdo->lastInsertId();
            $this->insertItems($orderId, $items);

            $pdo->commit();

            return $orderId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        DatabaseConnection::get()
            ->prepare('UPDATE orders SET status = :status WHERE id = :id')
            ->execute(['id' => $id, 'status' => $status]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM orders WHERE id = :id')->execute(['id' => $id]);
    }

    private function insertItems(int $orderId, array $items): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
             VALUES (:order_id, :product_id, :quantity, :unit_price, :line_total)'
        );

        foreach ($items as $item) {
            $stmt->execute([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['quantity'] * $item['unit_price'],
            ]);
        }
    }
}
