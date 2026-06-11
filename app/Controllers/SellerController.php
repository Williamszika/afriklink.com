<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Avatar;
use App\Models\ProProfile;
use App\Request;

/**
 * Espace vendeur — tableau de bord à menu latéral. Chaque entrée du menu est
 * une vraie URL (navigation serveur, fonctionne sans JavaScript) ; la vue de
 * section rend la barre latérale partagée + son contenu à droite.
 */
final class SellerController
{
    /**
     * Données communes à toutes les sections (identité, logo, complétion).
     * @return array<string,mixed>
     */
    public static function commonData(array $user): array
    {
        $userId        = (int) ($user['id'] ?? 0);
        $profile       = ProProfile::findByUserId($userId) ?? [];
        $avatarVersion = Avatar::versionFor($userId);

        return [
            'user'           => $user,
            'profile'        => $profile,
            'avatar_version' => $avatarVersion,
            'avatar_url'     => avatar_url($user, $avatarVersion),
            'completion'     => self::completion($profile, $avatarVersion !== null),
        ];
    }

    /** % de complétion : logo + 6 champs entreprise (sert barre + checklist). */
    public static function completion(array $profile, bool $hasLogo): int
    {
        $done = $hasLogo ? 1 : 0;
        foreach (['description', 'legal_form', 'reg_number', 'address', 'website', 'languages'] as $f) {
            if (!empty($profile[$f])) {
                $done++;
            }
        }
        return (int) round($done * 100 / 7);
    }

    public function storefronts(Request $request): void
    {
        view('vendeur/vitrines', ['active' => 'vitrines'] + self::commonData(current_user() ?? []));
    }

    public function orders(Request $request): void
    {
        view('vendeur/commandes', ['active' => 'commandes'] + self::commonData(current_user() ?? []));
    }

    public function messages(Request $request): void
    {
        view('vendeur/messages', ['active' => 'messages'] + self::commonData(current_user() ?? []));
    }
}
