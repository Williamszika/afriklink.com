<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * users table — accounts (a single account can be buyer AND vendor).
 * All queries are prepared (security.md §1). Soft-deleted rows are never returned.
 */
final class User
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public static function findByPublicId(string $publicId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM users WHERE public_id = :pid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['pid' => $publicId]);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    public static function findByPhone(string $phone): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM users WHERE phone = :phone AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['phone' => $phone]);
        return $stmt->fetch() ?: null;
    }

    public static function phoneExists(string $phone): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE phone = :phone LIMIT 1');
        $stmt->execute(['phone' => $phone]);
        return (bool) $stmt->fetchColumn();
    }

    /** Look up by email (if the identifier contains '@') or by normalised phone. */
    public static function findByEmailOrPhone(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        return str_contains($identifier, '@')
            ? self::findByEmail(strtolower($identifier))
            : self::findByPhone(normalize_phone($identifier));
    }

    /**
     * Create a user. Expects an already-hashed password.
     * @return int new user id
     */
    public static function create(array $data): int
    {
        self::ensureGenderOther();
        $stmt = db()->prepare(
            'INSERT INTO users
                (public_id, email, phone, password_hash, role, account_type, full_name, nickname,
                 birthdate, gender, gender_other, locale, country_code, city, preferred_currency, status)
             VALUES
                (:public_id, :email, :phone, :password_hash, :role, :account_type, :full_name, :nickname,
                 :birthdate, :gender, :gender_other, :locale, :country_code, :city, :preferred_currency, :status)'
        );
        $stmt->execute([
            'public_id'          => uuid(),
            'email'              => $data['email'] ?? null,
            'phone'              => $data['phone'] ?? null,
            'password_hash'      => $data['password_hash'],
            'role'               => $data['role'] ?? 'user',
            'account_type'       => $data['account_type'] ?? 'particulier',
            'full_name'          => $data['full_name'] ?? null,
            'nickname'           => $data['nickname'] ?? null,
            'birthdate'          => $data['birthdate'] ?? null,
            'gender'             => $data['gender'] ?? null,
            'gender_other'       => $data['gender_other'] ?? null,
            'locale'             => $data['locale'] ?? 'fr',
            'country_code'       => $data['country_code'] ?? null,
            'city'               => $data['city'] ?? null,
            'preferred_currency' => $data['preferred_currency'] ?? 'EUR',
            'status'             => $data['status'] ?? 'active',
        ]);
        return (int) db()->lastInsertId();
    }

    /** Précision libre quand le genre choisi est « autre » (colonne à la volée). */
    private static function ensureGenderOther(): void
    {
        try {
            db()->query('SELECT gender_other FROM users LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE users ADD COLUMN gender_other VARCHAR(40) NULL');
            } catch (\Throwable) {
                // course entre instances : une autre requête a déjà migré
            }
        }
    }

    /** Compteur de versions de session (colonne à la volée) : incrémenté à chaque
     *  changement de mot de passe pour invalider les AUTRES sessions actives. */
    private static function ensureSessionEpoch(): void
    {
        try {
            db()->query('SELECT session_epoch FROM users LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE users ADD COLUMN session_epoch INT UNSIGNED NOT NULL DEFAULT 0');
            } catch (\Throwable) {
                // pas de droits DDL (prod) : la migration SQL s'en charge ; en
                // attendant, l'époque vaut 0 partout (lecture sûre, fonction inerte).
            }
        }
    }

    /** Époque de session courante d'un compte (0 si non migrée). */
    public static function sessionEpoch(int $id): int
    {
        self::ensureSessionEpoch();
        try {
            $stmt = db()->prepare('SELECT session_epoch FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $v = $stmt->fetchColumn();
            return $v === false ? 0 : (int) $v;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Invalide toutes les AUTRES sessions du compte (changement de mot de passe).
     *  Renvoie la nouvelle époque (à reposer dans la session de l'acteur courant). */
    public static function bumpSessionEpoch(int $id): int
    {
        self::ensureSessionEpoch();
        try {
            db()->prepare('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = :id')->execute(['id' => $id]);
        } catch (\Throwable) {
        }
        return self::sessionEpoch($id);
    }

    public static function markEmailVerified(int $id): void
    {
        $stmt = db()->prepare(
            'UPDATE users SET email_verified_at = NOW() WHERE id = :id AND email_verified_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $stmt->execute(['h' => $passwordHash, 'id' => $id]);
    }

    /**
     * Update the editable profile fields of an account (never contact/credentials).
     * `updated_at` refreshes automatically (ON UPDATE CURRENT_TIMESTAMP).
     */
    public static function updateProfile(int $id, array $data): void
    {
        self::ensureGenderOther();
        $stmt = db()->prepare(
            'UPDATE users SET
                full_name    = :full_name,
                nickname     = :nickname,
                birthdate    = :birthdate,
                gender       = :gender,
                gender_other = :gender_other,
                country_code = :country_code,
                city         = :city
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'full_name'    => $data['full_name'] ?? null,
            'nickname'     => $data['nickname'] ?? null,
            'birthdate'    => $data['birthdate'] ?? null,
            'gender'       => $data['gender'] ?? null,
            'gender_other' => $data['gender_other'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'city'         => $data['city'] ?? null,
            'id'           => $id,
        ]);
    }

    public static function isEmailVerified(array $user): bool
    {
        return !empty($user['email_verified_at']);
    }

    /**
     * Localisation détectée (géolocalisation), rattachée au compte pour être
     * réutilisée sur tout le site et à chaque visite, sur n'importe quel
     * appareil. Distincte de l'adresse de profil (city/country_code) que
     * l'utilisateur renseigne lui-même. Colonnes créées à la volée (TiDB).
     */
    public static function setDetectedLocation(int $id, array $geo): void
    {
        self::ensureGeoColumns();
        $stmt = db()->prepare(
            'UPDATE users SET
                geo_city         = :city,
                geo_country_code = :cc,
                geo_continent    = :continent,
                geo_lat          = :lat,
                geo_lng          = :lng,
                geo_updated_at   = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'city'      => $geo['city'] ?? null,
            'cc'        => $geo['country_code'] ?? null,
            'continent' => $geo['continent'] ?? null,
            'lat'       => $geo['lat'] ?? null,
            'lng'       => $geo['lng'] ?? null,
            'id'        => $id,
        ]);
    }

    /** Ajoute les colonnes de localisation détectée si absentes (idempotent). */
    private static function ensureGeoColumns(): void
    {
        try {
            db()->query('SELECT geo_country_code FROM users LIMIT 1');
        } catch (\Throwable) {
            try {
                db()->exec('ALTER TABLE users
                    ADD COLUMN geo_city         VARCHAR(80) NULL,
                    ADD COLUMN geo_country_code CHAR(2) NULL,
                    ADD COLUMN geo_continent    VARCHAR(16) NULL,
                    ADD COLUMN geo_lat          DECIMAL(9,6) NULL,
                    ADD COLUMN geo_lng          DECIMAL(9,6) NULL,
                    ADD COLUMN geo_updated_at   DATETIME NULL');
            } catch (\Throwable) {
                // course entre instances : une autre requête a déjà migré
            }
        }
    }

    /** Persist interface language + display currency (cookies drive the request). */
    public static function updatePreferences(int $id, string $locale, string $currency): void
    {
        $stmt = db()->prepare(
            'UPDATE users SET locale = :locale, preferred_currency = :currency
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['locale' => $locale, 'currency' => $currency, 'id' => $id]);
    }
}
