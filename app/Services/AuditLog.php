<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Writes to the audit_log table for sensitive actions (security.md §11):
 * logins, password changes, role/privilege changes, admin actions, payouts...
 * Best-effort — auditing must never break the primary action.
 */
final class AuditLog
{
    public static function record(
        ?int $actorUserId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $meta = [],
        ?string $ipBinary = null,
    ): void {
        try {
            $stmt = db()->prepare(
                'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, ip, meta)
                 VALUES (:actor, :action, :ttype, :tid, :ip, :meta)'
            );
            $stmt->bindValue('actor', $actorUserId, $actorUserId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue('action', $action);
            $stmt->bindValue('ttype', $targetType);
            $stmt->bindValue('tid', $targetId, $targetId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue('ip', $ipBinary, $ipBinary === null ? \PDO::PARAM_NULL : \PDO::PARAM_LOB);
            $stmt->bindValue('meta', $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null);
            $stmt->execute();
        } catch (\Throwable $e) {
            log_message('warning', 'audit_log write failed: ' . $e->getMessage(), ['action' => $action]);
        }
    }
}
