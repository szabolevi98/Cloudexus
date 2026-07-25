<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;
use Cloudexus\Core\Paginator;

class ParameterModel
{
    public function all(): array
    {
        return DatabaseConnection::get()->query('SELECT * FROM parameters ORDER BY name ASC')->fetchAll();
    }

    /** Filters: q (name). */
    public function paginate(array $filters, Paginator $pager): array
    {
        $where = [];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = 'name LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['updated_since'])) {
            $where[] = 'updated_at >= :updated_since';
            $params['updated_since'] = $filters['updated_since'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = DatabaseConnection::get()->prepare("SELECT COUNT(*) FROM parameters $whereSql");
        $count->execute($params);
        $pager->total = (int) $count->fetchColumn();
        $pager->clamp();

        $stmt = DatabaseConnection::get()->prepare(
            "SELECT * FROM parameters $whereSql ORDER BY name ASC LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM parameters WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM parameters WHERE name = :name';
        $params = ['name' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = DatabaseConnection::get()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $name): int
    {
        DatabaseConnection::get()
            ->prepare('INSERT INTO parameters (name, created_at) VALUES (:name, NOW())')
            ->execute(['name' => $name]);
        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, string $name): void
    {
        DatabaseConnection::get()
            ->prepare('UPDATE parameters SET name = :name WHERE id = :id')
            ->execute(['id' => $id, 'name' => $name]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM parameters WHERE id = :id')->execute(['id' => $id]);
    }

    /** Select2 AJAX search over parameter names; results use the name as both id and text. */
    public function search(string $q, int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT id, name FROM parameters WHERE name LIKE :q ORDER BY name ASC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue('q', '%' . $q . '%');
        $stmt->bindValue('lim', $perPage + 1, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $more = count($rows) > $perPage;
        $rows = array_slice($rows, 0, $perPage);

        // A termékparaméterek id-t tárolnak, ezért a Select2 is id-t kap vissza.
        return [
            'results' => array_map(fn(array $r) => ['id' => (int) $r['id'], 'text' => $r['name']], $rows),
            'more' => $more,
        ];
    }
}
