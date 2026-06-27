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
                last_body      VARCHAR(1024) NULL,
                last_sender_id BIGINT UNSIGNED NULL,
                last_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                buyer_read_at  DATETIME NULL,
                seller_read_at DATETIME NULL,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                pair_key       VARCHAR(40) NULL,
                KEY idx_conv_buyer (buyer_id, last_at),
                KEY idx_conv_seller (seller_id, last_at),
                UNIQUE KEY uniq_dm_pair (pair_key)
            )'
        );
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS messages (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_id       BIGINT UNSIGNED NOT NULL,
                body            TEXT NOT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_msg_conv (conversation_id, id)
            )'
        );
        self::migrateColumns();
    }

    /**
     * Élargit les colonnes existantes (le chiffrement produit des blobs plus
     * longs que le texte clair). Idempotent et vérifié une seule fois par requête.
     */
    private static function migrateColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        try {
            $bodyType = db()->query(
                "SELECT DATA_TYPE FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'body'"
            )->fetchColumn();
            if ($bodyType !== false && strtolower((string) $bodyType) !== 'text') {
                ddl_safe('ALTER TABLE messages MODIFY body TEXT NOT NULL');
            }
            $lbLen = db()->query(
                "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversations' AND COLUMN_NAME = 'last_body'"
            )->fetchColumn();
            if ($lbLen !== false && (int) $lbLen < 1024) {
                ddl_safe('ALTER TABLE conversations MODIFY last_body VARCHAR(1024) NULL');
            }
            // Anti-doublon des fils DIRECTS : colonne pair_key (« min-max » des deux
            // membres, NULL pour les fils boutique/produit → MySQL autorise plusieurs
            // NULL, pas de collision) + index UNIQUE.
            $hasPair = db()->query(
                "SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversations' AND COLUMN_NAME = 'pair_key' LIMIT 1"
            )->fetchColumn();
            if ($hasPair === false) {
                ddl_safe('ALTER TABLE conversations ADD COLUMN pair_key VARCHAR(40) NULL');
            }
            $hasIdx = db()->query(
                "SELECT 1 FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversations' AND INDEX_NAME = 'uniq_dm_pair' LIMIT 1"
            )->fetchColumn();
            if ($hasIdx === false) {
                ddl_safe('ALTER TABLE conversations ADD UNIQUE KEY uniq_dm_pair (pair_key)');
            }
        } catch (\Throwable) {
            // information_schema indisponible : on ignore (les CREATE neufs sont déjà corrects).
        }
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

    /**
     * Conversation DIRECTE entre deux membres (hors boutique/produit), pour la
     * messagerie d'utilisateur à utilisateur. Insensible au sens : (A→B) et
     * (B→A) partagent le même fil. Crée si besoin (initiateur = buyer_id).
     * @return array
     */
    public static function findOrCreateDirect(int $initiatorId, int $targetId, ?string $subject): array
    {
        self::ensureTables();
        $pdo = db();
        // Verrou applicatif sur la PAIRE TRIÉE : sérialise les créations
        // concurrentes (double envoi / double-clic) afin de ne jamais créer deux
        // fils directs en double pour le même couple de membres.
        $lock = 'afk_dm_' . min($initiatorId, $targetId) . '_' . max($initiatorId, $targetId);
        $got  = 0;
        try {
            $lk = $pdo->prepare('SELECT GET_LOCK(:k, 5)');
            $lk->execute(['k' => $lock]);
            $got = (int) $lk->fetchColumn();
        } catch (\Throwable) {
            $got = 0;
        }
        try {
            // Recherche de la conversation directe existante (insensible au sens).
            $sel = static function () use ($pdo, $initiatorId, $targetId) {
                $st = $pdo->prepare(
                    'SELECT * FROM conversations
                      WHERE boutique_id IS NULL AND product_id IS NULL
                        AND ( (buyer_id = :a AND seller_id = :b) OR (buyer_id = :b2 AND seller_id = :a2) )
                      LIMIT 1'
                );
                $st->execute(['a' => $initiatorId, 'b' => $targetId, 'b2' => $targetId, 'a2' => $initiatorId]);
                return $st->fetch();
            };
            $row = $sel();
            if ($row !== false) {
                return $row;
            }
            $pid     = uuid();
            $subj    = $subject !== null && $subject !== '' ? mb_substr($subject, 0, 150) : null;
            $pairKey = min($initiatorId, $targetId) . '-' . max($initiatorId, $targetId);
            try {
                // pair_key UNIQUE : même si le verrou n'a pas été obtenu (timeout),
                // une 2ᵉ création concurrente pour la MÊME paire échoue en base →
                // on récupère alors la conversation gagnante (jamais de doublon).
                $pdo->prepare(
                    'INSERT INTO conversations (public_id, buyer_id, seller_id, boutique_id, product_id, subject, last_at, pair_key)
                     VALUES (:pid, :b, :s, NULL, NULL, :subj, NOW(), :pk)'
                )->execute(['pid' => $pid, 'b' => $initiatorId, 's' => $targetId, 'subj' => $subj, 'pk' => $pairKey]);
                return self::findByPublicId($pid) ?? [];
            } catch (\Throwable $e) {
                // Course gagnée par une autre requête (doublon) → on renvoie l'existante.
                $row = $sel();
                if ($row !== false) {
                    return $row;
                }
                // Colonne pair_key non migrée (SQLSTATE 42S22) → insert hérité SANS
                // pair_key. Toute AUTRE erreur (transitoire, colonne présente) → on
                // reprend AVEC pair_key, protégé par l'index unique : on n'émet jamais
                // de ligne à pair_key NULL qui pourrait créer un doublon.
                $colMissing = ($e instanceof \PDOException) && ($e->getCode() === '42S22');
                if ($colMissing) {
                    $pdo->prepare(
                        'INSERT INTO conversations (public_id, buyer_id, seller_id, boutique_id, product_id, subject, last_at)
                         VALUES (:pid, :b, :s, NULL, NULL, :subj, NOW())'
                    )->execute(['pid' => $pid, 'b' => $initiatorId, 's' => $targetId, 'subj' => $subj]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO conversations (public_id, buyer_id, seller_id, boutique_id, product_id, subject, last_at, pair_key)
                         VALUES (:pid, :b, :s, NULL, NULL, :subj, NOW(), :pk)'
                    )->execute(['pid' => $pid, 'b' => $initiatorId, 's' => $targetId, 'subj' => $subj, 'pk' => $pairKey]);
                }
                return self::findByPublicId($pid) ?? [];
            }
        } finally {
            if ($got === 1) {
                try {
                    $pdo->prepare('SELECT RELEASE_LOCK(:k)')->execute(['k' => $lock]);
                } catch (\Throwable) {
                }
            }
        }
    }
    public static function post(int $conversationId, int $senderId, string $body): int
    {
        self::ensureTables();
        $body = mb_substr(trim($body), 0, 2000);
        // Chiffrement AU REPOS : le corps et l'aperçu sont stockés chiffrés ; une
        // fuite de la base ne révèle rien sans APP_KEY.
        db()->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (:c, :s, :b)')
            ->execute(['c' => $conversationId, 's' => $senderId, 'b' => \App\Services\Crypto::encrypt($body)]);
        $id = (int) db()->lastInsertId();
        // L'expéditeur a « lu » sa propre conversation ; aperçu (chiffré) mis à jour.
        db()->prepare(
            'UPDATE conversations
                SET last_body = :lb, last_sender_id = :sid, last_at = NOW(),
                    buyer_read_at  = CASE WHEN buyer_id  = :sid2 THEN NOW() ELSE buyer_read_at  END,
                    seller_read_at = CASE WHEN seller_id = :sid3 THEN NOW() ELSE seller_read_at END
              WHERE id = :c'
        )->execute([
            'lb' => \App\Services\Crypto::encrypt(mb_substr($body, 0, 160)), 'sid' => $senderId,
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
            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as &$r) {
                if (isset($r['last_body'])) {
                    $r['last_body'] = \App\Services\Crypto::decrypt((string) $r['last_body']);
                }
            }
            unset($r);
            return $rows;
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
            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as &$r) {
                $r['body'] = \App\Services\Crypto::decrypt((string) ($r['body'] ?? ''));
            }
            unset($r);
            return $rows;
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
