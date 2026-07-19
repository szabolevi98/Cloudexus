<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class PartnerModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query(
            'SELECT p.*, g.name AS customer_group_name
             FROM partners p
             LEFT JOIN customer_groups g ON g.id = p.customer_group_id
             ORDER BY p.name ASC'
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT p.*, g.name AS customer_group_name
             FROM partners p
             LEFT JOIN customer_groups g ON g.id = p.customer_group_id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Filters: q (name/tax_number/email), type ('customer'|'supplier'|'both'), status, customer_group_id.
     */
    public function paginate(array $filters, \Cloudexus\Core\Paginator $pager): array
    {
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = '(p.name LIKE :q1 OR p.tax_number LIKE :q2 OR p.email LIKE :q3)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }
        if ($filters['type'] !== '') {
            $where[] = 'p.type = :type';
            $params['type'] = $filters['type'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'p.is_active = :is_active';
            $params['is_active'] = $filters['status'] === 'active' ? 1 : 0;
        }
        if (!empty($filters['customer_group_id'])) {
            $where[] = 'p.customer_group_id = :group_id';
            $params['group_id'] = (int) $filters['customer_group_id'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM partners p $whereSql");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT p.*, g.name AS customer_group_name
             FROM partners p
             LEFT JOIN customer_groups g ON g.id = p.customer_group_id
             $whereSql ORDER BY p.name ASC LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
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
            'INSERT INTO partners (type, customer_group_id, name, tax_number, email, phone, address, is_active, created_at)
             VALUES (:type, :customer_group_id, :name, :tax_number, :email, :phone, :address, :is_active, NOW())'
        );
        $stmt->execute([
            'type' => $data['type'],
            'customer_group_id' => !empty($data['customer_group_id']) ? $data['customer_group_id'] : null,
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
            'UPDATE partners SET type = :type, customer_group_id = :customer_group_id, name = :name, tax_number = :tax_number, email = :email,
                phone = :phone, address = :address, is_active = :is_active, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'type' => $data['type'],
            'customer_group_id' => !empty($data['customer_group_id']) ? $data['customer_group_id'] : null,
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
