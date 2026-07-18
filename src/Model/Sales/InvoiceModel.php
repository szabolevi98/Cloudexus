<?php

namespace Cloudexus\Model\Sales;

use Cloudexus\Core\DatabaseConnection;

class InvoiceModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT i.*, p.name AS partner_name
             FROM invoices i
             JOIN partners p ON p.id = i.partner_id
             ORDER BY i.created_at DESC'
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT i.*, p.name AS partner_name, p.tax_number, p.address
             FROM invoices i
             JOIN partners p ON p.id = i.partner_id
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

    public function create(array $data, array $items): int
    {
        $pdo = DatabaseConnection::get();
        $pdo->beginTransaction();

        try {
            $total = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));

            $stmt = $pdo->prepare(
                'INSERT INTO invoices (invoice_number, order_id, partner_id, status, issue_date, due_date, total_amount, created_by, created_at)
                 VALUES (:invoice_number, :order_id, :partner_id, :status, :issue_date, :due_date, :total_amount, :created_by, NOW())'
            );
            $stmt->execute([
                'invoice_number' => $data['invoice_number'],
                'order_id' => $data['order_id'] ?: null,
                'partner_id' => $data['partner_id'],
                'status' => $data['status'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'total_amount' => $total,
                'created_by' => $data['created_by'] ?: null,
            ]);

            $invoiceId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, line_total)
                 VALUES (:invoice_id, :product_id, :quantity, :unit_price, :line_total)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'invoice_id' => $invoiceId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
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

    public function outstandingTotal(): float
    {
        return (float) DatabaseConnection::get()
            ->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'unpaid'")
            ->fetchColumn();
    }
}
