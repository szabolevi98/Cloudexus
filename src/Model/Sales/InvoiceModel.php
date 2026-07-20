<?php

namespace Cloudexus\Model\Sales;

use Cloudexus\Core\DatabaseConnection;
use Cloudexus\Core\Paginator;

class InvoiceModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT i.*, p.name AS partner_name
             FROM invoices i
             JOIN partners p ON p.id = i.partner_id
             ORDER BY i.issue_date DESC, i.id DESC'
        )->fetchAll();
    }

    /** Filters: q (invoice_number), partner_id, status, date_from, date_to (issue_date). */
    public function paginate(array $filters, Paginator $pager): array
    {
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = 'i.invoice_number LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['partner_id'])) {
            $where[] = 'i.partner_id = :partner_id';
            $params['partner_id'] = (int) $filters['partner_id'];
        }
        if ($filters['status'] !== '') {
            if ($filters['status'] === 'overdue') {
                $where[] = "i.status = 'unpaid' AND i.due_date < CURDATE()";
            } else {
                $where[] = 'i.status = :status';
                $params['status'] = $filters['status'];
            }
        }
        if ($filters['date_from'] !== '') {
            $where[] = 'i.issue_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'i.issue_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM invoices i $whereSql");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT i.*, p.name AS partner_name
             FROM invoices i
             JOIN partners p ON p.id = i.partner_id
             $whereSql
             ORDER BY i.issue_date DESC, i.id DESC
             LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT i.*, p.name AS partner_name, p.tax_number, p.address, w.name AS warehouse_name
             FROM invoices i
             JOIN partners p ON p.id = i.partner_id
             LEFT JOIN warehouses w ON w.id = i.warehouse_id
             WHERE i.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return null;
        }

        $invoice['items'] = $this->items($id);

        return $invoice;
    }

    public function items(int $invoiceId): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT ii.*, pr.sku, pr.name AS product_name, pr.unit
             FROM invoice_items ii
             JOIN products pr ON pr.id = ii.product_id
             WHERE ii.invoice_id = :invoice_id'
        );
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    public function nextInvoiceNumber(): string
    {
        $year = date('Y');
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT COUNT(*) FROM invoices WHERE invoice_number LIKE :pattern'
        );
        $stmt->execute(['pattern' => "SZLA-$year-%"]);
        $count = (int) $stmt->fetchColumn() + 1;

        return sprintf('SZLA-%s-%04d', $year, $count);
    }

    /**
     * Creates the invoice; when warehouse_id is set, also books each line item
     * as a stock-out movement from that warehouse (availability is expected to
     * be validated by the caller before calling this).
     */
    public function create(array $data, array $items): int
    {
        $pdo = DatabaseConnection::get();
        $pdo->beginTransaction();

        try {
            $shippingCost = (float) ($data['shipping_cost'] ?? 0);
            $paymentCost = (float) ($data['payment_cost'] ?? 0);
            $total = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items)) + $shippingCost + $paymentCost;

            $stmt = $pdo->prepare(
                'INSERT INTO invoices (invoice_number, order_id, partner_id, warehouse_id, status, issue_date, due_date, total_amount, shipping_cost, payment_cost, created_by, created_at)
                 VALUES (:invoice_number, :order_id, :partner_id, :warehouse_id, :status, :issue_date, :due_date, :total_amount, :shipping_cost, :payment_cost, :created_by, NOW())'
            );
            $stmt->execute([
                'invoice_number' => $data['invoice_number'],
                'order_id' => $data['order_id'] ?: null,
                'partner_id' => $data['partner_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'status' => $data['status'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'total_amount' => $total,
                'shipping_cost' => $shippingCost,
                'payment_cost' => $paymentCost,
                'created_by' => $data['created_by'] ?: null,
            ]);

            $invoiceId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, line_total)
                 VALUES (:invoice_id, :product_id, :quantity, :unit_price, :line_total)'
            );
            $stockStmt = $pdo->prepare(
                "INSERT INTO stock_movements (warehouse_id, product_id, type, quantity, note, created_by, created_at)
                 VALUES (:warehouse_id, :product_id, 'out', :quantity, :note, :created_by, :created_at)"
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    'invoice_id' => $invoiceId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);

                if (!empty($data['warehouse_id'])) {
                    $stockStmt->execute([
                        'warehouse_id' => $data['warehouse_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'note' => 'Értékesítés: ' . $data['invoice_number'],
                        'created_by' => $data['created_by'] ?: null,
                        'created_at' => $data['issue_date'] . ' ' . date('H:i:s'),
                    ]);
                }
            }

            if (!empty($data['order_id'])) {
                $pdo->prepare("UPDATE orders SET status = 'invoiced' WHERE id = :id")
                    ->execute(['id' => $data['order_id']]);
            }

            $pdo->commit();

            return $invoiceId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        DatabaseConnection::get()
            ->prepare('UPDATE invoices SET status = :status WHERE id = :id')
            ->execute(['id' => $id, 'status' => $status]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM invoices WHERE id = :id')->execute(['id' => $id]);
    }

    public function unpaidList(): array
    {
        return DatabaseConnection::get()->query(
            "SELECT i.*, p.name AS partner_name
             FROM invoices i
             JOIN partners p ON p.id = i.partner_id
             WHERE i.status = 'unpaid'
             ORDER BY i.due_date ASC"
        )->fetchAll();
    }

    public function recent(int $limit = 6): array
    {
        return DatabaseConnection::get()->query(
            'SELECT i.*, p.name AS partner_name
             FROM invoices i
             JOIN partners p ON p.id = i.partner_id
             ORDER BY i.issue_date DESC, i.id DESC
             LIMIT ' . (int) $limit
        )->fetchAll();
    }

    public function outstandingTotal(): float
    {
        return (float) DatabaseConnection::get()
            ->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'unpaid'")
            ->fetchColumn();
    }

    /** Unpaid total split into overdue (past due date) and current. */
    public function outstandingBreakdown(): array
    {
        $row = DatabaseConnection::get()->query(
            "SELECT COALESCE(SUM(total_amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() THEN total_amount ELSE 0 END), 0) AS overdue
             FROM invoices WHERE status = 'unpaid'"
        )->fetch();

        return [
            'total' => (float) $row['total'],
            'overdue' => (float) $row['overdue'],
            'current' => (float) $row['total'] - (float) $row['overdue'],
        ];
    }
}
