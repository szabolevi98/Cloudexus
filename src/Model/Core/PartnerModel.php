<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class PartnerModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query('SELECT * FROM partners ORDER BY name ASC')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Filters: q (name/tax_number/email), type ('customer'|'supplier'|'both'), status.
     */
    public function paginate(array $filters, \Cloudexus\Core\Paginator $pager): array
    {
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = '(name LIKE :q1 OR tax_number LIKE :q2 OR email LIKE :q3)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }
        if ($filters['type'] !== '') {
            $where[] = 'type = :type';
            $params['type'] = $filters['type'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = $filters['status'] === 'active' ? 1 : 0;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM partners $whereSql");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT * FROM partners $whereSql ORDER BY name ASC LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function customersAndBoth(): array
    {
        return DatabaseConnection::get()
            ->query("SELECT * FROM partners WHERE type IN ('customer', 'both') AND is_active = 1 ORDER BY name ASC")
            ->fetchAll();
    }

    public function suppliersAndBoth(): array
    {
        return DatabaseConnection::get()
            ->query("SELECT * FROM partners WHERE type IN ('supplier', 'both') AND is_active = 1 ORDER BY name ASC")
            ->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO partners (type, name, tax_number, email, phone, address, is_active, created_at)
             VALUES (:type, :name, :tax_number, :email, :phone, :address, :is_active, NOW())'
        );
        $stmt->execute([
            'type' => $data['type'],
            'name' => $data['name'],
            'tax_number' => $data['tax_number'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'is_active' => $data['is_active'],
        ]);

        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = DatabaseConnection::get()->prepare(
            'UPDATE partners SET type = :type, name = :name, tax_number = :tax_number, email = :email,
                phone = :phone, address = :address, is_active = :is_active, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'type' => $data['type'],
            'name' => $data['name'],
            'tax_number' => $data['tax_number'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'is_active' => $data['is_active'],
        ]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM partners WHERE id = :id')->execute(['id' => $id]);
    }
}
