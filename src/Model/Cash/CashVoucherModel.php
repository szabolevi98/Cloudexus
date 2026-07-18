<?php

namespace Cloudexus\Model\Cash;

use Cloudexus\Core\DatabaseConnection;

class CashVoucherModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT v.*, p.name AS partner_name,
                    i.invoice_number AS sales_invoice_number,
                    ii.invoice_number AS incoming_invoice_number
             FROM cash_vouchers v
             LEFT JOIN partners p ON p.id = v.partner_id
             LEFT JOIN invoices i ON i.id = v.invoice_id
             LEFT JOIN incoming_invoices ii ON ii.id = v.incoming_invoice_id
             ORDER BY v.created_at DESC'
        )->fetchAll();
    }

    public function nextVoucherNumber(): string
    {
        $year = date('Y');
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT COUNT(*) FROM cash_vouchers WHERE voucher_number LIKE :pattern'
        );
        $stmt->execute(['pattern' => "PB-$year-%"]);
        $count = (int) $stmt->fetchColumn() + 1;

        return sprintf('PB-%s-%04d', $year, $count);
    }

    public function create(array $data): int
    {
        $pdo = DatabaseConnection::get();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO cash_vouchers (voucher_number, type, amount, partner_id, invoice_id, incoming_invoice_id, note, voucher_date, created_by, created_at)
                 VALUES (:voucher_number, :type, :amount, :partner_id, :invoice_id, :incoming_invoice_id, :note, :voucher_date, :created_by, NOW())'
            );
            $stmt->execute([
                'voucher_number' => $data['voucher_number'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'partner_id' => $data['partner_id'] ?: null,
                'invoice_id' => $data['invoice_id'] ?: null,
                'incoming_invoice_id' => $data['incoming_invoice_id'] ?: null,
                'note' => $data['note'] ?: null,
                'voucher_date' => $data['voucher_date'],
                'created_by' => $data['created_by'] ?: null,
            ]);

            $id = (int) $pdo->lastInsertId();

            if (!empty($data['invoice_id'])) {
                $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = :id")
                    ->execute(['id' => $data['invoice_id']]);
            }

            if (!empty($data['incoming_invoice_id'])) {
                $pdo->prepare("UPDATE incoming_invoices SET status = 'paid' WHERE id = :id")
                    ->execute(['id' => $data['incoming_invoice_id']]);
            }

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM cash_vouchers WHERE id = :id')->execute(['id' => $id]);
    }

    public function currentBalance(): float
    {
        return (float) DatabaseConnection::get()->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'bevetel' THEN amount ELSE -amount END), 0) FROM cash_vouchers"
        )->fetchColumn();
    }
}
