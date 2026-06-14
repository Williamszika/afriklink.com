<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\NewsletterSubscriber;
use App\Request;

/** Inscription à la lettre d'information (capture d'e-mail, ex. pied de page). */
final class NewsletterController
{
    public function subscribe(Request $request): void
    {
        $email = (string) input_string('email', '');
        $ok = NewsletterSubscriber::subscribe($email, current_locale(), 'footer');
        flash($ok ? 'success' : 'error', t($ok ? 'newsletter.thanks' : 'newsletter.invalid'));
        redirect($this->backTo());
    }

    /** Revient sur la page d'origine (referer interne) ou l'accueil. */
    private function backTo(): string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($ref !== '' && $host !== '' && str_contains($ref, $host)) {
            $path = (string) parse_url($ref, PHP_URL_PATH);
            if ($path !== '') {
                return $path;
            }
        }
        return '/';
    }
}
