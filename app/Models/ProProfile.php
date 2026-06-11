<?php
declare(strict_types=1);

namespace App\Models;

/**
 * pro_profiles — la fiche entreprise d'un compte professionnel (1 par compte).
 * Les vitrines (boutique, restaurant, salon, service) seront des entités
 * séparées rattachées au même compte ; ici on ne stocke que l'identité légale
 * et le contact de l'entreprise. Table auto-créée (comme listings/avatars).
 */
final class ProProfile
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS pro_profiles (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id             BIGINT UNSIGNED NOT NULL UNIQUE,
                company_name        VARCHAR(150) NOT NULL,
                legal_name          VARCHAR(150) NULL,
                legal_form          VARCHAR(24) NOT NULL,
                reg_number          VARCHAR(64) NULL,
                vat_number          VARCHAR(32) NULL,
                description         VARCHAR(600) NULL,
                address             VARCHAR(220) NULL,
                website             VARCHAR(200) NULL,
                languages           VARCHAR(60) NULL,
                whatsapp_optin      TINYINT(1) NOT NULL DEFAULT 0,
                verification_status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )'
        );
    }

    public static function create(int $userId, array $data): void
    {
        self::ensureTable();
        $stmt = db()->prepare(
            'INSERT INTO pro_profiles
                (user_id, company_name, legal_name, legal_form, reg_number, vat_number,
                 description, address, website, languages, whatsapp_optin, verification_status)
             VALUES
                (:user_id, :company_name, :legal_name, :legal_form, :reg_number, :vat_number,
                 :description, :address, :website, :languages, :whatsapp_optin, \'pending\')'
        );
        $stmt->execute([
            'user_id'        => $userId,
            'company_name'   => $data['company_name'],
            'legal_name'     => $data['legal_name'],
            'legal_form'     => $data['legal_form'],
            'reg_number'     => $data['reg_number'],
            'vat_number'     => $data['vat_number'],
            'description'    => $data['description'],
            'address'        => $data['address'],
            'website'        => $data['website'],
            'languages'      => $data['languages'],
            'whatsapp_optin' => $data['whatsapp_optin'] ? 1 : 0,
        ]);
    }

    public static function findByUserId(int $userId): ?array
    {
        try {
            $stmt = db()->prepare('SELECT * FROM pro_profiles WHERE user_id = :uid LIMIT 1');
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable) {
            return null; // table pas encore créée
        }
    }
}
