<?php
declare(strict_types=1);

namespace App\Models;

/**
 * login_attempts — record of authentication attempts for brute-force detection
 * and forensics (security.md §6). Best-effort: never breaks the auth flow.
 */
final class LoginAttempt
{
    public static function record(?string $email, ?string $ipBinary, bool $success): void
    {
        try {
            $stmt = db()->prepare(
                'INSERT INTO login_attempts (email, ip, success) VALUES (:email, :ip, :success)'
            );
            $stmt->bindValue('email', $email);
            $stmt->bindValue('ip', $ipBinary, \PDO::PARAM_LOB);
            $stmt->bindValue('success', $success ? 1 : 0, \PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            log_message('warning', 'login_attempt log failed: ' . $e->getMessage());
        }
    }

    /** Count recent failed attempts for an email within the last N seconds. */
    public static function recentFailures(string $email, int $withinSeconds): int
    {
        $since = (new \DateTimeImmutable('now'))
            ->modify('-' . $withinSeconds . ' seconds')
            ->format('Y-m-d H:i:s');

        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :email AND success = 0 AND created_at > :since'
        );
        $stmt->execute(['email' => $email, 'since' => $since]);
        return (int) $stmt->fetchColumn();
    }
}
