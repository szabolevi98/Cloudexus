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
             ORDER BY o.order_date DESC, o.id DESC'
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

    /**
     * Daily order count/value for the last $days days (including today), oldest first.
     * Cancelled orders are excluded.
     */
    public function dailyTotals(int $days = 10): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            "SELECT order_date, COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS total_value
             FROM orders
             WHERE status != 'cancelled' AND order_date >= :from
             GROUP BY order_date"
        );
        $stmt->execute(['from' => date('Y-m-d', strtotime("-$days days"))]);
        $rows = array_column($stmt->fetchAll(), null, 'order_date');

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $result[] = [
                'date' => $date,
                'order_count' => (int) ($rows[$date]['order_count'] ?? 0),
                'total_value' => (float) ($rows[$date]['total_value'] ?? 0),
            ];
        }

        return $result;
    }

    /** Filters: q (order_number), partner_id, status, date_from, date_to. */
    public function paginate(array $filters, \Cloudexus\Core\Paginator $pager): array
    {
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = 'o.order_number LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['partner_id'])) {
            $where[] = 'o.partner_id = :partner_id';
            $params['partner_id'] = (int) $filters['partner_id'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'o.status = :status';
            $params['status'] = $filters['status'];
        }
        if ($filters['date_from'] !== '') {
            $where[] = 'o.order_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'o.order_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM orders o $whereSql");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT o.*, p.name AS partner_name
             FROM orders o
             JOIN partners p ON p.id = o.partner_id
             $whereSql
             ORDER BY o.order_date DESC, o.id DESC
             LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Top product categories by ordered value in the last $days days. */
    public function topCategories(int $days = 30, int $limit = 6): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            "SELECT COALESCE(c.name, 'Kategorizálatlan') AS name, SUM(oi.line_total) AS value
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
             JOIN products p ON p.id = oi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE o.order_date >= :from
             GROUP BY c.id, c.name
             ORDER BY value DESC
             LIMIT " . (int) $limit
        );
        $stmt->execute(['from' => date('Y-m-d', strtotime("-$days days"))]);

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
