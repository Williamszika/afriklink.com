<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

/**
 * Pages « piliers de confiance » publiques : chaque garantie de la page d'accueil
 * (paiements sécurisés, vendeurs vérifiés, local & international, assistance) a sa
 * page qui explique le SYSTÈME derrière, et renvoie vers la fonctionnalité réelle.
 */
final class TrustController
{
    public function payments(Request $request): void
    {
        $this->render('payments');
    }

    public function verified(Request $request): void
    {
        $this->render('verified');
    }

    public function intl(Request $request): void
    {
        $this->render('intl');
    }

    public function support(Request $request): void
    {
        $this->render('support');
    }

    private function render(string $pillar): void
    {
        view('trust/pillar', [
            'pillar'     => $pillar,
            'page_title' => t('trust.' . $pillar . '.title'),
            'meta'       => ['description' => t('trust.' . $pillar . '.lead')],
        ]);
    }
}
