<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Effacement de compte (RGPD Art. 17 « droit à l'effacement »), équilibré avec
 * les obligations légales de conservation.
 *
 * Stratégie « anonymisation d'abord, suppression douce » :
 *   1. Contenu vendeur mis HORS LIGNE (statut « deleted »).
 *   2. Avis : le texte reste (utile aux autres acheteurs), l'auteur est anonymisé.
 *   3. Messagerie : conversations + messages de la personne supprimés.
 *   4. Données purement personnelles supprimées (adresses, photo, notifications,
 *      vérifications, réinitialisations, KYC, alertes, newsletter…).
 *   5. Écritures financières/comptables CONSERVÉES (commandes, paiements,
 *      portefeuille) — rétention légale — désormais rattachées à un compte
 *      anonymisé.
 *   6. Ligne `users` anonymisée + `deleted_at` (le compte n'est plus jamais
 *      renvoyé par les requêtes, e-mail/téléphone libérés).
 *
 * Chaque étape secondaire est « best-effort » (une table absente n'interrompt
 * pas l'effacement) ; seule l'anonymisation de la ligne `users` est critique.
 */
final class AccountEraser
{
    /** @param array<string,mixed> $user  ligne users courante */
    public static function erase(int $userId, array $user): void
    {
        // 1) Contenu vendeur hors ligne (colonne status = VARCHAR, valeur « deleted »
        //    déjà utilisée ailleurs ; les requêtes publiques filtrent active/published).
        foreach (['boutiques', 'listings', 'products', 'restaurants', 'ad_campaigns'] as $table) {
            self::run("UPDATE {$table} SET status = 'deleted' WHERE user_id = :id", ['id' => $userId]);
        }

        // 2) Avis : conserver le contenu, anonymiser l'auteur (colonne dénormalisée).
        self::run('UPDATE reviews SET author_name = :anon, user_id = NULL WHERE user_id = :id',
            ['anon' => '—', 'id' => $userId]);

        // 3) Messagerie : supprimer messages puis conversations où la personne est partie.
        self::run('DELETE m FROM messages m JOIN conversations c ON m.conversation_id = c.id
                   WHERE c.buyer_id = :id OR c.seller_id = :id', ['id' => $userId]);
        self::run('DELETE FROM messages WHERE sender_id = :id', ['id' => $userId]);
        self::run('DELETE FROM conversations WHERE buyer_id = :id OR seller_id = :id', ['id' => $userId]);

        // 4) Données purement personnelles (par user_id).
        foreach ([
            'user_addresses', 'user_avatars', 'notifications', 'email_verifications',
            'password_resets', 'boutique_delete_codes', 'kyc_submissions',
        ] as $table) {
            self::run("DELETE FROM {$table} WHERE user_id = :id", ['id' => $userId]);
        }

        // 5) Par e-mail / téléphone (données non rattachées au user_id).
        $email = (string) ($user['email'] ?? '');
        $phone = (string) ($user['phone'] ?? '');
        if ($email !== '') {
            self::run('DELETE FROM newsletter_subscribers WHERE email = :e', ['e' => $email]);
            self::run('DELETE FROM login_attempts WHERE email = :e', ['e' => $email]);
            self::run('DELETE FROM abandoned_carts WHERE email = :e OR user_id = :id', ['e' => $email, 'id' => $userId]);
            self::run('DELETE FROM stock_alerts WHERE email = :e', ['e' => $email]);
        } else {
            self::run('DELETE FROM abandoned_carts WHERE user_id = :id', ['id' => $userId]);
        }
        if ($phone !== '') {
            self::run('DELETE FROM stock_alerts WHERE phone = :p', ['p' => $phone]);
        }

        // 6) Anonymisation + suppression douce de la ligne users (CRITIQUE).
        self::anonymizeUser($userId);
    }

    /**
     * Écrase les identifiants et les données personnelles de la ligne `users`,
     * pose `deleted_at` et rend le mot de passe inutilisable. Ne touche qu'aux
     * colonnes garanties par le schéma ; les colonnes optionnelles (créées à la
     * volée) sont nettoyées séparément en best-effort.
     */
    private static function anonymizeUser(int $userId): void
    {
        $unusable = password_hash(bin2hex(random_bytes(32)), password_algo());

        // CRITIQUE : efface les données personnelles + suppression douce.
        // N'utilise QUE des colonnes garanties par le schéma de base (pas `status`,
        // qui est un ENUM dont la liste peut varier). C'est `deleted_at` qui retire
        // définitivement le compte de toutes les requêtes (User::findById filtre
        // `deleted_at IS NULL`) ; e-mail/téléphone sont libérés pour un futur compte.
        $stmt = db()->prepare(
            'UPDATE users SET
                email         = NULL,
                phone         = NULL,
                full_name     = NULL,
                nickname      = NULL,
                birthdate     = NULL,
                gender        = NULL,
                city          = NULL,
                country_code  = NULL,
                password_hash = :pw,
                deleted_at    = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['pw' => $unusable, 'id' => $userId]);

        // Best-effort : marquer inactif (valeur d'ENUM valide) + colonnes optionnelles
        // (créées à la volée selon l'instance). Une valeur d'ENUM inconnue ou une
        // colonne absente ne doit jamais faire échouer l'effacement.
        self::run("UPDATE users SET status = 'suspended' WHERE id = :id", ['id' => $userId]);
        self::run('UPDATE users SET gender_other = NULL WHERE id = :id', ['id' => $userId]);
        self::run(
            'UPDATE users SET geo_city = NULL, geo_country_code = NULL, geo_continent = NULL,
                              geo_lat = NULL, geo_lng = NULL, geo_updated_at = NULL
             WHERE id = :id', ['id' => $userId]);
    }

    /**
     * Exécute une écriture en best-effort (avale les erreurs de table/colonne
     * absente) et renvoie le nombre de lignes affectées.
     * @param array<string,mixed> $params
     */
    private static function run(string $sql, array $params): int
    {
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
