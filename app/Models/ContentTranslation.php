<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Traductions stockées du CONTENU vendeur (noms/descriptions de produits et
 * boutiques). Clé : (ref_type, ref_id, field, locale). On garde un hash de la
 * source (src_hash) pour re-traduire uniquement quand le vendeur modifie le
 * texte. Affichage via le helper tr_content().
 */
final class ContentTranslation
{
    /** Cache par requête : [type][id][field][locale] => texte. */
    private static array $cache = [];
    /** La table contient-elle au moins une ligne ? (évite les requêtes inutiles tant qu'aucune traduction n'existe). */
    private static ?bool $active = null;

    public static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS content_translations (
                    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    ref_type  VARCHAR(16) NOT NULL,
                    ref_id    BIGINT UNSIGNED NOT NULL,
                    field     VARCHAR(24) NOT NULL,
                    locale    VARCHAR(5)  NOT NULL,
                    text      TEXT NOT NULL,
                    src_hash  CHAR(32) NOT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_ct (ref_type, ref_id, field, locale)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
            );
        } catch (\Throwable) {
            // collation indispo sur MariaDB : réessai sans COLLATE explicite
            try {
                db()->exec(
                    'CREATE TABLE IF NOT EXISTS content_translations (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        ref_type VARCHAR(16) NOT NULL, ref_id BIGINT UNSIGNED NOT NULL,
                        field VARCHAR(24) NOT NULL, locale VARCHAR(5) NOT NULL,
                        text TEXT NOT NULL, src_hash CHAR(32) NOT NULL,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id), UNIQUE KEY uq_ct (ref_type, ref_id, field, locale)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                );
            } catch (\Throwable) {
                // on réessaiera au prochain appel
            }
        }
        $done = true;
    }

    /** Y a-t-il au moins une traduction stockée ? (mémoïsé par requête). */
    public static function active(): bool
    {
        if (self::$active !== null) {
            return self::$active;
        }
        try {
            self::ensureTable();
            self::$active = (bool) db()->query('SELECT 1 FROM content_translations LIMIT 1')->fetchColumn();
        } catch (\Throwable) {
            self::$active = false;
        }
        return self::$active;
    }

    /** Traduction stockée pour la langue donnée, ou null. */
    public static function get(string $type, int $id, string $field, string $locale): ?string
    {
        if (isset(self::$cache[$type][$id][$field]) && array_key_exists($locale, self::$cache[$type][$id][$field])) {
            return self::$cache[$type][$id][$field][$locale];
        }
        try {
            $stmt = db()->prepare(
                'SELECT text FROM content_translations WHERE ref_type=:t AND ref_id=:i AND field=:f AND locale=:l LIMIT 1'
            );
            $stmt->execute(['t' => $type, 'i' => $id, 'f' => $field, 'l' => $locale]);
            $val = $stmt->fetchColumn();
            $out = $val !== false ? (string) $val : null;
        } catch (\Throwable) {
            $out = null;
        }
        self::$cache[$type][$id][$field][$locale] = $out;
        return $out;
    }

    /**
     * Pré-charge en une requête toutes les traductions d'un lot d'objets pour une
     * langue (évite le N+1 sur les grilles de produits).
     * @param list<int> $ids
     */
    public static function preload(string $type, array $ids, string $locale): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $n): bool => $n > 0)));
        if ($ids === [] || !self::active()) {
            return;
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = db()->prepare(
                "SELECT ref_id, field, text FROM content_translations
                  WHERE ref_type=? AND locale=? AND ref_id IN ($place)"
            );
            $stmt->execute(array_merge([$type, $locale], $ids));
            // initialise le cache (null) pour tous les ids demandés, puis remplit
            foreach ($ids as $id) {
                self::$cache[$type][$id]['name'][$locale]        ??= null;
                self::$cache[$type][$id]['description'][$locale] ??= null;
                self::$cache[$type][$id]['tagline'][$locale]     ??= null;
            }
            foreach ($stmt->fetchAll() ?: [] as $r) {
                self::$cache[$type][(int) $r['ref_id']][(string) $r['field']][$locale] = (string) $r['text'];
            }
        } catch (\Throwable) {
            // pas de pré-chargement : get() retombera sur des requêtes unitaires
        }
    }

    /** Enregistre/replace une traduction. */
    public static function put(string $type, int $id, string $field, string $locale, string $text, string $srcHash): void
    {
        self::ensureTable();
        try {
            db()->prepare(
                'INSERT INTO content_translations (ref_type, ref_id, field, locale, text, src_hash)
                 VALUES (:t,:i,:f,:l,:x,:h)
                 ON DUPLICATE KEY UPDATE text=VALUES(text), src_hash=VALUES(src_hash), updated_at=CURRENT_TIMESTAMP'
            )->execute(['t' => $type, 'i' => $id, 'f' => $field, 'l' => $locale, 'x' => $text, 'h' => $srcHash]);
            self::$cache[$type][$id][$field][$locale] = $text;
            self::$active = true;
        } catch (\Throwable) {
            // ignore : la traduction sera retentée au prochain passage du cron
        }
    }

    /** Une traduction à jour existe-t-elle pour ce hash source ? (utilisé par le cron). */
    public static function hasFresh(string $type, int $id, string $field, string $locale, string $srcHash): bool
    {
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM content_translations WHERE ref_type=:t AND ref_id=:i AND field=:f AND locale=:l AND src_hash=:h LIMIT 1'
            );
            $stmt->execute(['t' => $type, 'i' => $id, 'f' => $field, 'l' => $locale, 'h' => $srcHash]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
