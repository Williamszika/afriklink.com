<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

/**
 * Pages légales (mentions, confidentialité/RGPD, CGV/CGU) et enregistrement du
 * consentement aux cookies. Modèles à faire valider par un conseil juridique.
 */
final class LegalController
{
    public function notice(Request $request): void
    {
        view('legal/mentions', ['page_title' => t('legal.notice.title')]);
    }

    public function privacy(Request $request): void
    {
        view('legal/confidentialite', ['page_title' => t('legal.privacy.title')]);
    }

    public function terms(Request $request): void
    {
        view('legal/cgv', ['page_title' => t('legal.terms.title')]);
    }

    /**
     * Enregistre le choix de cookies (fonctionne sans JavaScript) puis revient
     * sur la page d'origine. « accepter » = tous ; « refuser » = essentiels seuls.
     */
    public function consent(Request $request): void
    {
        $choice = (string) $request->param('choice', '');
        $value  = match ($choice) {
            'accepter' => 'all',
            'refuser'  => 'essential',
            default    => null,
        };
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
}
