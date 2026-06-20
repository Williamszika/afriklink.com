<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\NewsletterSubscriber;
use App\Request;
use App\Services\MailService;

/** Inscription à la lettre d'information (capture d'e-mail) + désinscription RGPD. */
final class NewsletterController
{
    public function subscribe(Request $request): void
    {
        $token = NewsletterSubscriber::subscribe((string) input_string('email', ''), current_locale(), 'footer');
        flash($token !== null ? 'success' : 'error', t($token !== null ? 'newsletter.thanks' : 'newsletter.invalid'));
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
        $token = NewsletterSubscriber::subscribe($email, current_locale(), 'popup');
        if ($token !== null) {
            try {
                MailService::send($email, t('newsletter.mail_subject'), self::confirmHtml($token));
            } catch (\Throwable) {
                // best-effort : l'abonnement reste valable même si l'e-mail échoue
            }
        }
        json_response(['ok' => $token !== null, 'message' => $token !== null ? t('newsletter.subscribed') : t('newsletter.invalid')]);
    }

    /** Désinscription 1-clic (RGPD) : GET /desinscription/{token}. */
    public function unsubscribe(Request $request): void
    {
        $email = NewsletterSubscriber::unsubscribe((string) $request->param('token', ''));
        view('newsletter/unsubscribed', [
            'page_title' => t('newsletter.unsub_title'),
            'ok'         => $email !== null,
            'email'      => $email,
        ]);
    }

    /** E-mail de confirmation d'abonnement (gabarit de marque + lien de désinscription). */
    private static function confirmHtml(string $token): string
    {
        return render_partial('emails/base', [
            'subject'         => t('newsletter.mail_subject'),
            'preheader'       => t('newsletter.mail_pre'),
            'heading'         => t('newsletter.mail_heading'),
            'intro'           => e(t('newsletter.mail_intro')),
            'cta_url'         => url('/explorer'),
            'cta_label'       => t('newsletter.mail_cta'),
            'accent'          => 'gold',
            'unsubscribe_url' => NewsletterSubscriber::unsubscribeUrl($token),
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
