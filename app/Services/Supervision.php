<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Agent « Supervision / Ops » — 100 % déterministe (aucune IA, aucune clé). Lit
 * les vraies données et remonte ce qui demande ton attention : commandes en
 * retard, retraits à verser, KYC/annonces à modérer, ruptures imminentes. Le cron
 * t'envoie un digest par e-mail uniquement s'il y a quelque chose d'actionnable.
 *
 * L'agent SIGNALE ; toi (ou ta future recrue Trust & Safety) AGIS. Il ne décide
 * ni ne touche jamais à l'argent.
 */
final class Supervision
{
    /** @return array{alerts:list<array{key:string,level:string,count:int,label:string,href:string}>, stats:array<string,int>} */
    public static function report(): array
    {
        return ['alerts' => self::alerts(), 'stats' => self::stats()];
    }

    /** @return list<array{key:string,level:string,count:int,label:string,href:string}> */
    private static function alerts(): array
    {
        $a = [];
        $add = static function (string $key, string $level, int $count, string $label, string $href) use (&$a): void {
            if ($count > 0) {
                $a[] = ['key' => $key, 'level' => $level, 'count' => $count, 'label' => $label, 'href' => $href];
            }
        };

        // Commandes en attente de traitement (à confirmer / à expédier) depuis +48 h.
        $add('orders_late', 'warn', self::count(
            "SELECT COUNT(*) FROM orders WHERE status IN ('new','confirmed') AND created_at < (NOW() - INTERVAL 48 HOUR)"
        ), t('sup.alert.orders_late'), url('/admin'));

        // Retraits vendeurs en attente de versement (manuel).
        $add('withdrawals', 'warn', \App\Models\Wallet::pendingCount(), t('sup.alert.withdrawals'), url('/admin/retraits'));

        // KYC à vérifier.
        $add('kyc', 'warn', self::count("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'pending'"), t('sup.alert.kyc'), url('/admin/kyc'));

        // Annonces (modérateurs) à valider.
        $add('announcements', 'info', self::count("SELECT COUNT(*) FROM announcements WHERE status = 'pending'"), t('sup.alert.announcements'), url('/admin/annonces'));

        // Produits actifs bientôt en rupture (stock fini ≤ 3).
        $add('low_stock', 'info', self::count(
            "SELECT COUNT(*) FROM products WHERE status='active' AND stock IS NOT NULL AND stock <= 3"
        ), t('sup.alert.low_stock'), url('/admin'));

        return $a;
    }

    /** @return array<string,int> indicateurs « pouls » (7 derniers jours). */
    private static function stats(): array
    {
        return [
            'orders_7d'    => self::count("SELECT COUNT(*) FROM orders WHERE created_at >= (NOW() - INTERVAL 7 DAY)"),
            'boutiques_7d' => self::count("SELECT COUNT(*) FROM boutiques WHERE status='published' AND created_at >= (NOW() - INTERVAL 7 DAY)"),
            'products_7d'  => self::count("SELECT COUNT(*) FROM products WHERE status='active' AND created_at >= (NOW() - INTERVAL 7 DAY)"),
            'subscribers'  => self::count("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='subscribed'"),
        ];
    }

    private static function count(string $sql): int
    {
        try {
            return (int) db()->query($sql)->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Destinataires du digest = opérateurs (ADMIN_EMAILS). @return list<string> */
    public static function recipients(): array
    {
        $emails = (array) config('app.admin_emails', []);
        return array_values(array_filter(array_map(static fn ($e): string => trim((string) $e), $emails)));
    }
}
