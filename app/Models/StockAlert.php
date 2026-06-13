<?php
declare(strict_types=1);

namespace App\Models;

/**
 * stock_alerts — demandes « prévenez-moi du retour en stock ». Le client laisse
 * un e-mail et/ou un téléphone ; quand le vendeur réapprovisionne le produit,
 * on prévient les abonnés puis on purge la liste (notification unique).
 */
final class StockAlert
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS stock_alerts (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id   CHAR(36) NOT NULL UNIQUE,
                product_id  BIGINT UNSIGNED NOT NULL,
                boutique_id BIGINT UNSIGNED NOT NULL,
                email       VARCHAR(120) NULL,
                phone       VARCHAR(24) NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_stockalerts_product (product_id)
            )'
        );
    }

    /** Inscrit un client (dédup par e-mail/téléphone pour ce produit). */
    public static function subscribe(int $productId, int $boutiqueId, ?string $email, ?string $phone): void
    {
        self::ensureTable();
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(*) FROM stock_alerts WHERE product_id = :p
                   AND ((:e IS NOT NULL AND email = :e) OR (:ph IS NOT NULL AND phone = :ph))'
            );
            $stmt->execute(['p' => $productId, 'e' => $email, 'ph' => $phone]);
            if ((int) $stmt->fetchColumn() > 0) {
                return; // déjà inscrit
            }
        } catch (\Throwable) {
        }
        db()->prepare(
            'INSERT INTO stock_alerts (public_id, product_id, boutique_id, email, phone)
             VALUES (:pid, :p, :b, :e, :ph)'
        )->execute(['pid' => uuid(), 'p' => $productId, 'b' => $boutiqueId, 'e' => $email, 'ph' => $phone]);
    }

    /** @return list<array> abonnés en attente pour un produit */
    public static function pendingForProduct(int $productId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM stock_alerts WHERE product_id = :p ORDER BY id');
            $stmt->execute(['p' => $productId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function clearForProduct(int $productId): void
    {
        try {
            db()->prepare('DELETE FROM stock_alerts WHERE product_id = :p')->execute(['p' => $productId]);
        } catch (\Throwable) {
        }
    }

    public static function countForProduct(int $productId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM stock_alerts WHERE product_id = :p');
            $stmt->execute(['p' => $productId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
