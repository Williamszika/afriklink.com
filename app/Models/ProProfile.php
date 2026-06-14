<?php
declare(strict_types=1);

namespace App\Models;

/**
 * pro_profiles — la fiche entreprise d'un compte vendeur (1 par compte).
 * L'inscription ne demande que le nom commercial ; tout le reste (statut
 * juridique, n° d'enregistrement, adresse, site, langues…) se complète depuis
 * le tableau de bord (/vendeur/profil). Table auto-créée.
 */
final class ProProfile
{
    public static function ensureTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS pro_profiles (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id             BIGINT UNSIGNED NOT NULL UNIQUE,
                company_name        VARCHAR(150) NOT NULL,
                legal_name          VARCHAR(150) NULL,
                legal_form          VARCHAR(60) NULL,
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
        // Élargit legal_form (texte libre quand « Autre ») si encore en VARCHAR(24).
        try {
            $len = (int) db()->query(
                "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pro_profiles'
                    AND COLUMN_NAME = 'legal_form'"
            )->fetchColumn();
            if ($len > 0 && $len < 60) {
                db()->exec('ALTER TABLE pro_profiles MODIFY legal_form VARCHAR(60) NULL');
            }
        } catch (\Throwable) {
            // information_schema indisponible : on réessaiera
        }
        // Préférences vendeur (réglages) — ajoutées après coup, best-effort.
        $cols = [
            'notify_email'       => "ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1",
            'notify_sms'         => "ADD COLUMN notify_sms TINYINT(1) NOT NULL DEFAULT 1",
            'payout_method'      => "ADD COLUMN payout_method VARCHAR(16) NULL",
            'payout_destination' => "ADD COLUMN payout_destination VARCHAR(160) NULL",
        ];
        foreach ($cols as $col => $ddl) {
            try {
                db()->query("SELECT {$col} FROM pro_profiles LIMIT 1");
            } catch (\Throwable) {
                try {
                    db()->exec("ALTER TABLE pro_profiles {$ddl}");
                } catch (\Throwable) {
                    // déjà présent ou DDL indisponible en prod
                }
            }
        }
    }

    /**
     * Préférences du vendeur (réglages), avec valeurs par défaut sûres si les
     * colonnes ne sont pas encore provisionnées (notifications activées).
     * @return array{notify_email:bool,notify_sms:bool,payout_method:?string,payout_destination:?string}
     */
    public static function sellerPrefs(int $userId): array
    {
        $defaults = ['notify_email' => true, 'notify_sms' => true, 'payout_method' => null, 'payout_destination' => null];
        try {
            $stmt = db()->prepare('SELECT notify_email, notify_sms, payout_method, payout_destination FROM pro_profiles WHERE user_id = :u LIMIT 1');
            $stmt->execute(['u' => $userId]);
            $r = $stmt->fetch();
            if ($r === false) {
                return $defaults;
            }
            return [
                'notify_email'       => (int) ($r['notify_email'] ?? 1) === 1,
                'notify_sms'         => (int) ($r['notify_sms'] ?? 1) === 1,
                'payout_method'      => ($r['payout_method'] ?? null) ?: null,
                'payout_destination' => ($r['payout_destination'] ?? null) ?: null,
            ];
        } catch (\Throwable) {
            return $defaults;
        }
    }

    /** Enregistre les préférences vendeur (réglages). Best-effort. */
    public static function setSellerPrefs(int $userId, array $prefs): void
    {
        try {
            db()->prepare(
                'UPDATE pro_profiles SET notify_email = :ne, notify_sms = :ns,
                        payout_method = :pm, payout_destination = :pd WHERE user_id = :u'
            )->execute([
                'ne' => !empty($prefs['notify_email']) ? 1 : 0,
                'ns' => !empty($prefs['notify_sms']) ? 1 : 0,
                'pm' => ($prefs['payout_method'] ?? '') !== '' ? mb_substr((string) $prefs['payout_method'], 0, 16) : null,
                'pd' => ($prefs['payout_destination'] ?? '') !== '' ? mb_substr((string) $prefs['payout_destination'], 0, 160) : null,
                'u'  => $userId,
            ]);
        } catch (\Throwable) {
            // colonnes non provisionnées : sans gravité
        }
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
            'legal_name'     => $data['legal_name'] ?? null,
            'legal_form'     => $data['legal_form'] ?? null,
            'reg_number'     => $data['reg_number'] ?? null,
            'vat_number'     => $data['vat_number'] ?? null,
            'description'    => $data['description'] ?? null,
            'address'        => $data['address'] ?? null,
            'website'        => $data['website'] ?? null,
            'languages'      => $data['languages'] ?? null,
            'whatsapp_optin' => !empty($data['whatsapp_optin']) ? 1 : 0,
        ]);
    }

    /** Mise à jour depuis « Profil vendeur » (tableau de bord). */
    public static function update(int $userId, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE pro_profiles SET
                company_name = :company_name, legal_name = :legal_name,
                legal_form = :legal_form, reg_number = :reg_number,
                vat_number = :vat_number, description = :description,
                address = :address, website = :website, languages = :languages,
                whatsapp_optin = :whatsapp_optin
             WHERE user_id = :user_id'
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
            'whatsapp_optin' => !empty($data['whatsapp_optin']) ? 1 : 0,
        ]);
    }

    /** Sous-ensemble vérifié parmi des user_ids. @return array<int,true> map user_id => true */
    public static function verifiedAmong(array $userIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $userIds)));
        if ($ids === []) {
            return [];
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare("SELECT user_id FROM pro_profiles WHERE verification_status = 'verified' AND user_id IN ($in)");
            $stmt->execute($ids);
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $r) {
                $out[(int) $r['user_id']] = true;
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function setVerificationStatus(int $userId, string $status): void
    {
        try {
            $stmt = db()->prepare('UPDATE pro_profiles SET verification_status = :s WHERE user_id = :uid');
            $stmt->execute(['s' => $status, 'uid' => $userId]);
        } catch (\Throwable) {
            // table absente : sans effet
        }
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
