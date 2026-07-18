<?php

namespace Cloudexus\Model\Purchasing;

use Cloudexus\Core\DatabaseConnection;

class PurchaseOrderModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT po.*, p.name AS partner_name
             FROM purchase_orders po
             JOIN partners p ON p.id = po.partner_id
             ORDER BY po.order_date DESC, po.id DESC'
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT po.*, p.name AS partner_name
             FROM purchase_orders po
             JOIN partners p ON p.id = po.partner_id
             WHERE po.id = :id LIMIT 1'
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
            'SELECT poi.*, pr.sku, pr.name AS product_name, pr.unit
             FROM purchase_order_items poi
             JOIN products pr ON pr.id = poi.product_id
             WHERE poi.purchase_order_id = :order_id'
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function nextPoNumber(): string
    {
        $year = date('Y');
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT COUNT(*) FROM purchase_orders WHERE po_number LIKE :pattern'
        );
        $stmt->execute(['pattern' => "BESZ-$year-%"]);
        $count = (int) $stmt->fetchColumn() + 1;

        return sprintf('BESZ-%s-%04d', $year, $count);
    }

    public function create(array $data, array $items): int
    {
        $pdo = DatabaseConnection::get();
        $pdo->beginTransaction();

        try {
            $total = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));

            $stmt = $pdo->prepare(
                'INSERT INTO purchase_orders (po_number, partner_id, status, order_date, total_amount, created_by, created_at)
                 VALUES (:po_number, :partner_id, :status, :order_date, :total_amount, :created_by, NOW())'
            );
            $stmt->execute([
                'po_number' => $data['po_number'],
                'partner_id' => $data['partner_id'],
                'status' => $data['status'],
                'order_date' => $data['order_date'],
                'total_amount' => $total,
                'created_by' => $data['created_by'] ?: null,
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, line_total)
                 VALUES (:order_id, :product_id, :quantity, :unit_price, :line_total)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
            }

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
            ->prepare('UPDATE purchase_orders SET status = :status WHERE id = :id')
            ->execute(['id' => $id, 'status' => $status]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM purchase_orders WHERE id = :id')->execute(['id' => $id]);
    }
}
