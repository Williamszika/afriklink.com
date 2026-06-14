<?php
declare(strict_types=1);

namespace App\Models;

/**
 * payment_events — journal d'idempotence des webhooks PSP. Chaque événement
 * reçu d'un fournisseur (Stripe, CinetPay…) est identifié de façon UNIQUE par
 * (provider, event_id). On l'enregistre AVANT de le traiter : si l'insertion
 * échoue (doublon), c'est un rejeu → on ignore. Garantit qu'un paiement n'est
 * jamais confirmé deux fois, même si le PSP renvoie l'événement plusieurs fois.
 * Table auto-créée.
 */
final class PaymentEvent
{
    public static function ensureTable(): void
    {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS payment_events (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                provider    VARCHAR(20) NOT NULL,
                event_id    VARCHAR(120) NOT NULL,
                type        VARCHAR(60) NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_provider_event (provider, event_id)
            )'
        );
    }

    /**
     * Réserve l'événement : renvoie true s'il est NOUVEAU (à traiter), false si
     * c'est un rejeu (déjà vu). Atomique via la contrainte UNIQUE.
     */
    public static function firstTime(string $provider, string $eventId, string $type = ''): bool
    {
        if ($eventId === '') {
            return false; // sans identifiant, on ne peut pas dédupliquer → on n'agit pas
        }
        self::ensureTable();
        try {
            $stmt = db()->prepare(
                'INSERT INTO payment_events (provider, event_id, type) VALUES (:p, :e, :t)'
            );
            $stmt->execute(['p' => mb_substr($provider, 0, 20), 'e' => mb_substr($eventId, 0, 120), 't' => mb_substr($type, 0, 60) ?: null]);
            return true;
        } catch (\Throwable) {
            return false; // doublon (clé UNIQUE) ou DB indisponible : ne pas retraiter
        }
    }
}
