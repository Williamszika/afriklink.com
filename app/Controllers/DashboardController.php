<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

final class DashboardController
{
    public function index(Request $request): void
    {
        $user   = current_user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        // Espace vendeur : tableau de bord à menu latéral (section « Vue d'ensemble »).
        if (($user['account_type'] ?? '') === 'professionnel') {
            view('vendeur/overview', ['active' => 'overview', 'dash' => SellerController::dashboardState($user)]
                + SellerController::commonData($user));
            return;
        }

        [$completion, $missing] = $this->profileCompletion($user);

        $listings = \App\Models\Listing::forUser($userId, 3);
        view('dashboard', [
            'user'           => $user,
            'completion'     => $completion,
            'missing'        => $missing,
            'avatar_version' => \App\Models\Avatar::versionFor($userId),
            'counts'         => \App\Models\Listing::countsFor($userId),
            'recent'         => $listings,
            'recent_mains'   => \App\Models\Listing::mainPhotos(array_map(static fn (array $l): int => (int) $l['id'], $listings)),
            'purchases'      => \App\Models\Order::forUser($userId, 6),
            'purchase_count' => \App\Models\Order::countForUser($userId),
        ]);
    }

    /** Espace acheteur : tous mes achats (commandes passées en ligne). */
    public function purchases(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        view('purchases', [
            'user'       => $user,
            'orders'     => \App\Models\Order::forUser($uid, 50),
            'page_title' => t('purchases.title'),
        ]);
    }

    /**
     * Friendly interstitial for dashboard actions whose full feature is still on
     * the roadmap (selling, messaging). Keeps every button clickable and honest
     * instead of dead-disabled. The feature key is whitelisted.
     */
    public function comingSoon(Request $request): void
    {
        $feature = whitelist(
            (string) $request->param('feature', ''),
            ['messages', 'boutique', 'restaurant', 'salon', 'service'],
            null
        );
        if ($feature === null) {
            abort(404);
        }
        view('coming_soon', ['feature' => $feature]);
    }

    /**
     * Profile completion: share of filled profile fields, plus a verified contact
     * (a phone, or a verified e-mail). Returns [percent, list of missing i18n keys].
     *
     * @return array{0:int,1:list<string>}
     */
    private function profileCompletion(array $u): array
    {
        $contactOk = !empty($u['phone']) || !empty($u['email_verified_at']);
        $checks = [
            'field.full_name'        => !empty($u['full_name']),
            'field.nickname'         => !empty($u['nickname']),
            'field.country'          => !empty($u['country_code']),
            'field.city'             => !empty($u['city']),
            'field.birthdate'        => !empty($u['birthdate']),
            'field.gender'           => !empty($u['gender']),
            'dash.contact_verified'  => $contactOk,
        ];

        $done = count(array_filter($checks));
        $missing = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));

        return [(int) round($done * 100 / count($checks)), $missing];
    }
}
