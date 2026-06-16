<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\NewsletterSubscriber;
use App\Request;
use App\Services\MailService;

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

    /** Abonnement depuis l'encart (comptes par téléphone) : réponse JSON + e-mail de confirmation. */
    public function popup(Request $request): void
    {
        $email = input_email('email');
        if ($email === null) {
            json_response(['ok' => false, 'message' => t('newsletter.invalid')], 422);
            return;
        }
        $ok = NewsletterSubscriber::subscribe($email, current_locale(), 'popup');
        if ($ok) {
            try {
                MailService::send($email, t('newsletter.mail_subject'), self::confirmHtml());
            } catch (\Throwable) {
                // best-effort : l'abonnement reste valable même si l'e-mail échoue
            }
        }
        json_response(['ok' => $ok, 'message' => $ok ? t('newsletter.subscribed') : t('newsletter.invalid')]);
    }

    /** E-mail de confirmation d'abonnement (réutilise le gabarit de marque). */
    private static function confirmHtml(): string
    {
        return render_partial('emails/base', [
            'subject'   => t('newsletter.mail_subject'),
            'preheader' => t('newsletter.mail_pre'),
            'heading'   => t('newsletter.mail_heading'),
            'intro'     => e(t('newsletter.mail_intro')),
            'cta_url'   => url('/explorer'),
            'cta_label' => t('newsletter.mail_cta'),
            'accent'    => 'gold',
        ]);
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
