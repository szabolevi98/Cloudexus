<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;
use Cloudexus\Core\Paginator;

class UnitModel
{
    public function all(): array
    {
        return DatabaseConnection::get()
            ->query('SELECT * FROM units ORDER BY sort_order ASC, name ASC')
            ->fetchAll();
    }

    /** Filters: q (code/name). */
    public function paginate(array $filters, Paginator $pager): array
    {
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = '(code LIKE :q1 OR name LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM units $whereSql");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT * FROM units $whereSql ORDER BY sort_order ASC, name ASC LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM units WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM units WHERE code = :code';
        $params = ['code' => $code];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = DatabaseConnection::get()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        DatabaseConnection::get()->prepare(
            'INSERT INTO units (code, name, sort_order) VALUES (:code, :name, :sort)'
        )->execute(['code' => $data['code'], 'name' => $data['name'], 'sort' => $data['sort_order'] ?? 0]);
        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        DatabaseConnection::get()->prepare(
            'UPDATE units SET code = :code, name = :name, sort_order = :sort WHERE id = :id'
        )->execute(['id' => $id, 'code' => $data['code'], 'name' => $data['name'], 'sort' => $data['sort_order'] ?? 0]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM units WHERE id = :id')->execute(['id' => $id]);
    }
}
