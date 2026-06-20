<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\Wallet;
use App\Request;

/**
 * Tableau de bord ADMINISTRATEUR (staff) : vue d'ensemble de la plateforme —
 * statistiques globales, files d'attente à traiter (KYC, retraits, annonces) et
 * accès rapide aux outils d'administration. Réservé au staff (is_staff).
 */
final class AdminDashboardController
{
    public function index(Request $request): void
    {
        view('admin/dashboard', [
            'page_title' => t('admin.dash.title'),
            'me'         => current_user() ?? [],
            'stats'      => self::stats(),
            'pending'    => self::pending(),
        ]);
    }

    /** Statistiques globales de la plateforme (résilient : 0 si table absente). */
    private static function stats(): array
    {
        return [
            'users'       => self::count('SELECT COUNT(*) FROM users'),
            'sellers'     => self::count("SELECT COUNT(*) FROM users WHERE account_type = 'professionnel'"),
            'boutiques'   => self::count("SELECT COUNT(*) FROM boutiques WHERE status = 'published'"),
            'products'    => self::count("SELECT COUNT(*) FROM products WHERE status = 'active'"),
            'restaurants' => self::count("SELECT COUNT(*) FROM restaurants WHERE status = 'published'"),
            'listings'    => self::count("SELECT COUNT(*) FROM listings WHERE status = 'active'"),
            'orders'      => self::count("SELECT COUNT(*) FROM orders WHERE status <> 'cancelled'"),
            'reviews'     => self::count("SELECT COUNT(*) FROM reviews WHERE status = 'approved'"),
        ];
    }

    /** Files d'attente à traiter. */
    private static function pending(): array
    {
        return [
            'kyc'         => self::count("SELECT COUNT(*) FROM kyc_submissions WHERE status = 'pending'"),
            'withdrawals' => Wallet::pendingCount(),
            'ann'         => Announcement::pendingCount(),
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
}
