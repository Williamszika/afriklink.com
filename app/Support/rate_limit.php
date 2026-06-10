<?php
declare(strict_types=1);

/**
 * Rate limiting simple basé sur la table `rate_limits`.
 * Empêche brute force / abus sur login, inscription, reset, paiement, recherche.
 *
 * Table attendue :
 *   CREATE TABLE rate_limits (
 *     id BIGINT AUTO_INCREMENT PRIMARY KEY,
 *     bucket_key VARCHAR(191) NOT NULL UNIQUE,
 *     hits INT NOT NULL DEFAULT 0,
 *     window_start DATETIME NOT NULL
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * Usage :
 *   if (!rate_limit_ok('login:' . $ip, 5, 300)) { http_response_code(429); exit; }
 *
 * @param string $key        identifiant du seau (ex. "login:" . ip ou user id + route)
 * @param int    $max        nombre max de hits par fenêtre
 * @param int    $windowSecs durée de la fenêtre en secondes
 * @return bool  true si autorisé, false si limite atteinte
 */
function rate_limit_ok(string $key, int $max, int $windowSecs): bool
{
    $key = substr($key, 0, 191);
    $now = new DateTimeImmutable('now');

    $pdo = null;
    try {
        $pdo = db();          // dans le try : si la base est injoignable, on laisse
        $pdo->beginTransaction(); // passer (fail-open) au lieu de casser la page
        $stmt = $pdo->prepare(
            'SELECT id, hits, window_start FROM rate_limits WHERE bucket_key = :k FOR UPDATE'
        );
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();

        if ($row === false) {
            $ins = $pdo->prepare(
                'INSERT INTO rate_limits (bucket_key, hits, window_start)
                 VALUES (:k, 1, :ws)'
            );
            $ins->execute(['k' => $key, 'ws' => $now->format('Y-m-d H:i:s')]);
            $pdo->commit();
            return true;
        }

        $windowStart = new DateTimeImmutable($row['window_start']);
        $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();

        if ($elapsed > $windowSecs) {
            // nouvelle fenêtre
            $upd = $pdo->prepare(
                'UPDATE rate_limits SET hits = 1, window_start = :ws WHERE id = :id'
            );
            $upd->execute(['ws' => $now->format('Y-m-d H:i:s'), 'id' => $row['id']]);
            $pdo->commit();
            return true;
        }

        if ((int) $row['hits'] >= $max) {
            $pdo->commit();
            return false; // limite atteinte
        }

        $upd = $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = :id');
        $upd->execute(['id' => $row['id']]);
        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // En cas d'erreur, ne pas bloquer l'utilisateur légitime — mais logger.
        error_log('rate_limit error: ' . $ex->getMessage());
        return true;
    }
}
