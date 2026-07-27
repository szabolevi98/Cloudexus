<?php

namespace Cloudexus\Model\Account;

use Cloudexus\Core\DatabaseConnection;

/** API kérés-napló: rate limit számításához és biztonsági visszakereséshez. */
class ApiRequestLogModel
{
    /** Beszúr egy log-sort a kérés indulásakor, státusz/futásidő nélkül. Visszaadja a sor id-ját. */
    public function start(?int $apiUserId, string $method, string $path, string $ip): int
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO api_request_logs (api_user_id, method, path, ip_address, created_at)
             VALUES (:api_user_id, :method, :path, :ip, NOW())'
        );
        $stmt->execute([
            'api_user_id' => $apiUserId,
            'method' => $method,
            'path' => $path,
            'ip' => $ip,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** A kérés végén (shutdown function) rögzíti a végleges HTTP státuszt és futásidőt. */
    public function finish(int $id, int $statusCode, float $durationMs): void
    {
        DatabaseConnection::get()
            ->prepare('UPDATE api_request_logs SET status_code = :status, duration_ms = :duration WHERE id = :id')
            ->execute([
                'status' => $statusCode,
                'duration' => (int) round($durationMs),
                'id' => $id,
            ]);
    }

    /** Az adott API user kéréseinek száma az utolsó $windowSeconds másodpercben (rate limit alap). */
    public function countRecent(int $apiUserId, int $windowSeconds): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT COUNT(*) FROM api_request_logs
             WHERE api_user_id = :api_user_id AND created_at >= NOW() - INTERVAL :seconds SECOND'
        );
        $stmt->bindValue('api_user_id', $apiUserId, \PDO::PARAM_INT);
        $stmt->bindValue('seconds', $windowSeconds, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** Törli a $days napnál régebbi log-sorokat, visszaadja a törölt sorok számát. */
    public function purgeOlderThan(int $days): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'DELETE FROM api_request_logs WHERE created_at < NOW() - INTERVAL :days DAY'
        );
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
