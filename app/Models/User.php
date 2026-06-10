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
        $stmt = db()->prepare(
            'INSERT INTO users
                (public_id, email, phone, password_hash, role, account_type, full_name, nickname,
                 birthdate, gender, locale, country_code, city, preferred_currency, status)
             VALUES
                (:public_id, :email, :phone, :password_hash, :role, :account_type, :full_name, :nickname,
                 :birthdate, :gender, :locale, :country_code, :city, :preferred_currency, :status)'
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
            'locale'             => $data['locale'] ?? 'fr',
            'country_code'       => $data['country_code'] ?? null,
            'city'               => $data['city'] ?? null,
            'preferred_currency' => $data['preferred_currency'] ?? 'EUR',
            'status'             => $data['status'] ?? 'active',
        ]);
        return (int) db()->lastInsertId();
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
        $stmt = db()->prepare(
            'UPDATE users SET
                full_name    = :full_name,
                nickname     = :nickname,
                birthdate    = :birthdate,
                gender       = :gender,
                country_code = :country_code,
                city         = :city
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'full_name'    => $data['full_name'] ?? null,
            'nickname'     => $data['nickname'] ?? null,
            'birthdate'    => $data['birthdate'] ?? null,
            'gender'       => $data['gender'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'city'         => $data['city'] ?? null,
            'id'           => $id,
        ]);
    }

    public static function isEmailVerified(array $user): bool
    {
        return !empty($user['email_verified_at']);
    }
}
