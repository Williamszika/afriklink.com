<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Export RGPD (Art. 15 « accès » & Art. 20 « portabilité »).
 *
 * Rassemble TOUTES les données personnelles d'un compte dans une structure
 * exportable en JSON, lisible par la personne concernée. Strictement en
 * LECTURE : ne modifie jamais rien. Chaque table est interrogée en
 * « best-effort » — une table absente ou une colonne manquante n'interrompt
 * pas l'export (utile car certaines colonnes sont créées à la volée).
 */
final class AccountData
{
    /**
     * @param array<string,mixed> $user  ligne users courante
     * @return array<string,mixed>
     */
    public static function export(int $userId, array $user): array
    {
        $email = (string) ($user['email'] ?? '');

        return [
            'export' => [
                'genere_le'      => gmdate('c'),
                'compte_id'      => $user['public_id'] ?? null,
                'format'         => 'Export RGPD AfrikaLink v1 (JSON)',
                'base_legale'    => 'RGPD Art. 15 (droit d\'accès) & Art. 20 (portabilité)',
                'note'           => 'Les commandes et paiements sont conservés au titre des obligations comptables/légales.',
            ],
            'compte'         => self::scrubUser($user),
            'adresses'       => self::rows('SELECT * FROM user_addresses WHERE user_id = :id ORDER BY id', ['id' => $userId]),
            'commandes'      => self::rows('SELECT * FROM orders WHERE user_id = :id OR buyer_user_id = :id ORDER BY id', ['id' => $userId]),
            'paiements'      => self::rows(
                'SELECT id, public_id, kind, order_id, provider, provider_ref, amount_cents, currency, description, status, created_at
                   FROM payments WHERE user_id = :id ORDER BY id', ['id' => $userId]),
            'portefeuille'   => self::rows('SELECT * FROM wallet_entries WHERE user_id = :id ORDER BY id', ['id' => $userId]),
            'retraits'       => self::rows('SELECT * FROM wallet_withdrawals WHERE user_id = :id ORDER BY id', ['id' => $userId]),
            'avis'           => self::rows(
                'SELECT id, public_id, boutique_id, product_id, author_name, rating, comment, status, created_at
                   FROM reviews WHERE user_id = :id ORDER BY id', ['id' => $userId]),
            'boutiques'      => self::rows('SELECT * FROM boutiques WHERE user_id = :id', ['id' => $userId]),
            'annonces'       => self::rows('SELECT * FROM listings WHERE user_id = :id', ['id' => $userId]),
            'produits'       => self::rows('SELECT * FROM products WHERE user_id = :id', ['id' => $userId]),
            'restaurants'    => self::rows('SELECT * FROM restaurants WHERE user_id = :id', ['id' => $userId]),
            'kyc'            => self::rows(
                'SELECT id, level, status, doc_type, id_first_name, id_last_name, submitted_at, reviewed_at
                   FROM kyc_submissions WHERE user_id = :id', ['id' => $userId]),
            'fiche_pro'      => self::rows(
                'SELECT company_name, legal_name, legal_form, reg_number, vat_number, description,
                        address, website, languages, payout_method, payout_destination,
                        verification_status, created_at, updated_at
                   FROM pro_profiles WHERE user_id = :id', ['id' => $userId]),
            'notifications'  => self::rows(
                'SELECT id, type, title, body, link, read_at, created_at
                   FROM notifications WHERE user_id = :id ORDER BY id DESC LIMIT 500', ['id' => $userId]),
            'messages'       => self::messages($userId),
            'newsletter'     => $email === '' ? [] : self::rows(
                'SELECT email, locale, status, created_at FROM newsletter_subscribers WHERE email = :e', ['e' => $email]),
        ];
    }

    /** Retire les champs internes/sensibles de la ligne compte. */
    private static function scrubUser(array $u): array
    {
        unset($u['password_hash'], $u['session_epoch']);
        return $u;
    }

    /**
     * Messages écrits par l'utilisateur, DÉCHIFFRÉS pour son propre export
     * (les messages sont chiffrés au repos — voir Services\Crypto).
     * @return array<int,array<string,mixed>>
     */
    private static function messages(int $userId): array
    {
        $rows = self::rows('SELECT id, conversation_id, body, created_at FROM messages WHERE sender_id = :id ORDER BY id', ['id' => $userId]);
        foreach ($rows as &$r) {
            $body = (string) ($r['body'] ?? '');
            if (Crypto::isEncrypted($body)) {
                try {
                    $r['body'] = Crypto::decrypt($body, 'messages');
                } catch (\Throwable) {
                    $r['body'] = '[message chiffré — déchiffrement indisponible]';
                }
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Exécute un SELECT en best-effort et retourne les lignes (assoc).
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private static function rows(string $sql, array $params): array
    {
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
