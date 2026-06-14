<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Messagerie acheteur ↔ vendeur. Une conversation lie deux membres (acheteur,
 * vendeur), éventuellement à propos d'un produit/boutique ; les messages la
 * composent. Dénormalisation du dernier message (aperçu) pour une boîte de
 * réception rapide. Tables auto-créées.
 */
final class Conversation
{
    public static function ensureTables(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS conversations (
                id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                public_id      CHAR(36) NOT NULL UNIQUE,
                buyer_id       BIGINT UNSIGNED NOT NULL,
                seller_id      BIGINT UNSIGNED NOT NULL,
                boutique_id    BIGINT UNSIGNED NULL,
                product_id     BIGINT UNSIGNED NULL,
                subject        VARCHAR(150) NULL,
                last_body      VARCHAR(200) NULL,
                last_sender_id BIGINT UNSIGNED NULL,
                last_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                buyer_read_at  DATETIME NULL,
                seller_read_at DATETIME NULL,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_conv_buyer (buyer_id, last_at),
                KEY idx_conv_seller (seller_id, last_at)
            )'
        );
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS messages (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_id       BIGINT UNSIGNED NOT NULL,
                body            VARCHAR(2000) NOT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_msg_conv (conversation_id, id)
            )'
        );
    }

    /** Trouve la conversation (acheteur, vendeur, produit) ou la crée. @return array */
    public static function findOrCreate(int $buyerId, int $sellerId, ?int $boutiqueId, ?int $productId, ?string $subject): array
    {
        self::ensureTables();
        $sql = 'SELECT * FROM conversations WHERE buyer_id = :b AND seller_id = :s AND ';
        $args = ['b' => $buyerId, 's' => $sellerId];
        if ($productId !== null) {
            $sql .= 'product_id = :p';
            $args['p'] = $productId;
        } else {
            $sql .= 'product_id IS NULL';
        }
        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($args);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
        $pid = uuid();
        db()->prepare(
            'INSERT INTO conversations (public_id, buyer_id, seller_id, boutique_id, product_id, subject, last_at)
             VALUES (:pid, :b, :s, :bo, :p, :subj, NOW())'
        )->execute([
            'pid' => $pid, 'b' => $buyerId, 's' => $sellerId,
            'bo' => $boutiqueId, 'p' => $productId,
            'subj' => $subject !== null && $subject !== '' ? mb_substr($subject, 0, 150) : null,
        ]);
        return self::findByPublicId($pid) ?? [];
    }

    /** Ajoute un message et met à jour l'aperçu + la date. Renvoie l'id du message. */
    public static function post(int $conversationId, int $senderId, string $body): int
    {
        self::ensureTables();
        $body = mb_substr(trim($body), 0, 2000);
        db()->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (:c, :s, :b)')
            ->execute(['c' => $conversationId, 's' => $senderId, 'b' => $body]);
        $id = (int) db()->lastInsertId();
        // L'expéditeur a « lu » sa propre conversation ; aperçu mis à jour.
        db()->prepare(
            'UPDATE conversations
                SET last_body = :lb, last_sender_id = :sid, last_at = NOW(),
                    buyer_read_at  = CASE WHEN buyer_id  = :sid2 THEN NOW() ELSE buyer_read_at  END,
                    seller_read_at = CASE WHEN seller_id = :sid3 THEN NOW() ELSE seller_read_at END
              WHERE id = :c'
        )->execute([
            'lb' => mb_substr($body, 0, 200), 'sid' => $senderId,
            'sid2' => $senderId, 'sid3' => $senderId, 'c' => $conversationId,
        ]);
        return $id;
    }

    public static function findByPublicId(string $publicId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM conversations WHERE public_id = :p LIMIT 1');
            $stmt->execute(['p' => $publicId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array> conversations d'un membre (acheteur ou vendeur), récentes d'abord */
    public static function forUser(int $userId): array
    {
        self::ensureTables();
        try {
            $stmt = db()->prepare(
                'SELECT c.*,
                        bu.full_name AS buyer_name, bu.nickname AS buyer_nick,
                        se.full_name AS seller_name, se.nickname AS seller_nick
                   FROM conversations c
                   JOIN users bu ON bu.id = c.buyer_id
                   JOIN users se ON se.id = c.seller_id
                  WHERE c.buyer_id = :u OR c.seller_id = :u2
                  ORDER BY c.last_at DESC LIMIT 100'
            );
            $stmt->execute(['u' => $userId, 'u2' => $userId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array> messages d'une conversation (ordre chronologique) */
    public static function messages(int $conversationId): array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM messages WHERE conversation_id = :c ORDER BY id ASC LIMIT 500');
            $stmt->execute(['c' => $conversationId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function isParticipant(array $conv, int $userId): bool
    {
        return (int) ($conv['buyer_id'] ?? 0) === $userId || (int) ($conv['seller_id'] ?? 0) === $userId;
    }

    /** Marque la conversation comme lue pour ce membre (selon son rôle). */
    public static function markRead(array $conv, int $userId): void
    {
        $col = (int) $conv['buyer_id'] === $userId ? 'buyer_read_at'
            : ((int) $conv['seller_id'] === $userId ? 'seller_read_at' : null);
        if ($col === null) {
            return;
        }
        try {
            db()->prepare("UPDATE conversations SET {$col} = NOW() WHERE id = :c")->execute(['c' => (int) $conv['id']]);
        } catch (\Throwable) {
        }
    }

    /** Une conversation est-elle non lue pour ce membre ? */
    public static function isUnread(array $conv, int $userId): bool
    {
        if ((int) ($conv['last_sender_id'] ?? 0) === $userId || (int) ($conv['last_sender_id'] ?? 0) === 0) {
            return false;
        }
        $readAt = (int) $conv['buyer_id'] === $userId ? ($conv['buyer_read_at'] ?? null)
            : ($conv['seller_read_at'] ?? null);
        return $readAt === null || strtotime((string) $conv['last_at']) > strtotime((string) $readAt);
    }

    public static function unreadCountFor(int $userId): int
    {
        self::ensureTables();
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(*) FROM conversations
                  WHERE last_sender_id IS NOT NULL AND last_sender_id <> :u
                    AND ( (buyer_id  = :u2 AND (buyer_read_at  IS NULL OR last_at > buyer_read_at))
                       OR (seller_id = :u3 AND (seller_read_at IS NULL OR last_at > seller_read_at)) )'
            );
            $stmt->execute(['u' => $userId, 'u2' => $userId, 'u3' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Nom affichable d'un membre depuis une ligne jointe (full_name > nickname). */
    public static function displayName(?string $fullName, ?string $nick): string
    {
        $n = trim((string) ($fullName ?: $nick));
        return $n !== '' ? $n : 'Afriklink';
    }
}
