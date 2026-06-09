<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use SessionHandlerInterface;

/**
 * Database-backed PHP session handler (required on serverless hosts like Vercel,
 * where the local filesystem is ephemeral). Stores sessions in the `sessions` table.
 *
 * Registered in bootstrap when config('app.session_driver') === 'database'.
 * Uses the shared PDO connection from db() — all queries prepared.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $stmt = db()->prepare('SELECT payload FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $payload = $stmt->fetchColumn();
        return $payload === false ? '' : (string) $payload;
    }

    public function write(string $id, string $data): bool
    {
        // REPLACE INTO is an atomic upsert in MySQL / TiDB.
        $stmt = db()->prepare(
            'REPLACE INTO sessions (id, payload, last_activity) VALUES (:id, :payload, :ts)'
        );
        return $stmt->execute(['id' => $id, 'payload' => $data, 'ts' => time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = db()->prepare('DELETE FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = db()->prepare('DELETE FROM sessions WHERE last_activity < :threshold');
        $stmt->execute(['threshold' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
