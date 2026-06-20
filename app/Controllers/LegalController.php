<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

/**
 * Pages légales adaptées au pays du visiteur (mentions / Impressum,
 * confidentialité-RGPD, CGU-CGV, droit de rétractation) et enregistrement du
 * consentement aux cookies. Modèles à faire valider par un conseil juridique.
 *
 * Le pays peut être forcé via ?pays=DE|EU|CI|INTL (ou un code ISO) pour
 * afficher le régime applicable à un autre pays que celui détecté.
 */
final class LegalController
{
    public function notice(Request $request): void
    {
        view('legal/mentions', [
            'page_title' => t('legal.notice.title'),
            'forced_cc'  => $this->forcedCc(),
        ]);
    }

    public function privacy(Request $request): void
    {
        view('legal/confidentialite', [
            'page_title' => t('legal.privacy.title'),
            'forced_cc'  => $this->forcedCc(),
        ]);
    }

    public function terms(Request $request): void
    {
        view('legal/cgv', [
            'page_title' => t('legal.terms.title'),
            'forced_cc'  => $this->forcedCc(),
        ]);
    }

    /** Droit de rétractation (UE/EEE) + formulaire-type. */
    public function withdrawal(Request $request): void
    {
        view('legal/retractation', [
            'page_title' => t('legal.withdrawal.title'),
            'forced_cc'  => $this->forcedCc(),
        ]);
    }

    /**
     * Enregistre le choix de cookies (fonctionne sans JavaScript) puis revient
     * sur la page d'origine :
     *   accepter     → toutes les catégories ;
     *   refuser      → essentiels seuls ;
     *   personnaliser→ essentiels + catégories cochées (functional, analytics).
     */
    public function consent(Request $request): void
    {
        $choice = (string) $request->param('choice', '');

        if ($choice === 'personnaliser') {
            $cats = ['essential'];
            if (input_string('functional') !== null) {
                $cats[] = 'functional';
            }
            if (input_string('analytics') !== null) {
                $cats[] = 'analytics';
            }
            $value = implode(',', $cats);
        } else {
            $value = match ($choice) {
                'accepter' => 'all',
                'refuser'  => 'essential',
                default    => null,
            };
        }

        if ($value === null) {
            abort(404);
        }

        setcookie('cookie_consent', $value, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => request_is_https(),
            'httponly' => false, // lisible par le JS pour conditionner une future mesure d'audience
            'samesite' => 'Lax',
        ]);

        $to = trim((string) input_string('to', '/'));
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || preg_match('/[\x00-\x1f]/', $to)) {
            $to = '/';
        }
        redirect(mb_substr($to, 0, 300));
    }

    /**
     * Pays forcé via ?pays= : régime explicite (DE/EU/CI/INTL) ou code ISO
     * alpha-2. Renvoie null si absent/invalide (→ détection automatique).
     */
    private function forcedCc(): ?string
    {
        $p = strtoupper(trim((string) input_string('pays', '')));
        if ($p === '') {
            return null;
        }
        if (in_array($p, ['DE', 'EU', 'CI', 'INTL'], true)) {
            return $p;
        }
        return preg_match('/^[A-Z]{2}$/', $p) ? $p : null;
    }
}
