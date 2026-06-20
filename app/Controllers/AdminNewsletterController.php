<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\NewsletterSubscriber;
use App\Request;
use App\Services\AuditLog;
use App\Services\MailService;

/**
 * Régie marketing (back-office staff) : composer et envoyer une lettre
 * d'information aux abonnés. RGPD : chaque e-mail part avec le lien de
 * désinscription propre au destinataire ; seuls les abonnés actifs sont visés.
 * Un « envoi de test » permet de se prévisualiser avant de diffuser.
 */
final class AdminNewsletterController
{
    /** Plafond d'envoi synchrone par diffusion (évite un timeout serverless). */
    private const MAX_PER_SEND = 500;

    public function index(Request $request): void
    {
        view('admin/newsletter', [
            'page_title' => t('newsletter.admin_title'),
            'counts'     => NewsletterSubscriber::counts(),
            'me'         => trim((string) (current_user()['email'] ?? '')),
        ]);
    }

    /** Envoi : action 'test' (à soi-même) ou 'all' (aux abonnés actifs). */
    public function send(Request $request): void
    {
        $subject = trim((string) input_string('subject', ''));
        $message = trim((string) input_string('message', ''));
        $ctaUrl  = trim((string) input_string('cta_url', ''));
        $ctaLbl  = trim((string) input_string('cta_label', ''));
        $action  = whitelist((string) input_string('action', ''), ['test', 'all'], 'test');

        if ($subject === '' || $message === '') {
            flash('error', t('newsletter.admin_need_content'));
            redirect('/admin/newsletter');
        }

        // CTA optionnel : on n'accepte qu'un chemin interne (sécurité anti-phishing).
        $cta = null;
        if ($ctaUrl !== '' && str_starts_with($ctaUrl, '/')) {
            $cta = ['url' => url($ctaUrl), 'label' => $ctaLbl !== '' ? $ctaLbl : t('newsletter.admin_cta_default')];
        }

        // « Pépites du catalogue » : blocs auto générés depuis les vraies données.
        $picks = input_string('include_picks', '') !== null ? \App\Services\NewsletterContent::weeklyPicks() : '';

        if ($action === 'test') {
            $to = trim((string) (current_user()['email'] ?? ''));
            if ($to === '') {
                flash('error', t('admin.mail.no_email'));
                redirect('/admin/newsletter');
            }
            $html = self::buildHtml($subject, $message, $cta, NewsletterSubscriber::unsubscribeUrl('apercu-test'), $picks);
            MailService::send($to, '[TEST] ' . $subject, $html, $message);
            flash('success', t('newsletter.admin_test_sent', ['email' => $to]));
            redirect('/admin/newsletter');
        }

        // Diffusion réelle à tous les abonnés actifs.
        $recipients = NewsletterSubscriber::subscribed(self::MAX_PER_SEND);
        $sent = 0;
        foreach ($recipients as $r) {
            $html = self::buildHtml($subject, $message, $cta, NewsletterSubscriber::unsubscribeUrl((string) $r['token']), $picks);
            try {
                if (MailService::send((string) $r['email'], $subject, $html, $message)) {
                    $sent++;
                }
            } catch (\Throwable) {
                // best-effort : un échec n'interrompt pas la diffusion
            }
        }
        AuditLog::record((int) current_user_id(), 'newsletter.broadcast', 'newsletter', null, ['subject' => $subject, 'sent' => $sent], $request->ipBinary());
        flash('success', t('newsletter.admin_sent', ['n' => $sent]));
        redirect('/admin/newsletter');
    }

    /**
     * Construit l'e-mail marketing de marque : titre = sujet, corps = message
     * (texte → paragraphes), CTA optionnel, + lien de désinscription (RGPD).
     * @param array{url:string,label:string}|null $cta
     */
    private static function buildHtml(string $subject, string $message, ?array $cta, string $unsubUrl, string $extraHtml = ''): string
    {
        $paras = '';
        foreach (preg_split('/\n{2,}/', $message) ?: [$message] as $block) {
            $block = trim($block);
            if ($block !== '') {
                $paras .= '<p class="afk-p">' . nl2br(e($block)) . '</p>';
            }
        }
        return render_partial('emails/base', [
            'subject'         => $subject,
            'preheader'       => mb_substr(strip_tags($message), 0, 140),
            'heading'         => e($subject),
            'intro'           => '',
            'body'            => $paras . $extraHtml,
            'cta_url'         => $cta['url'] ?? '',
            'cta_label'       => $cta['label'] ?? '',
            'accent'          => 'gold',
            'unsubscribe_url' => $unsubUrl,
        ]);
    }
}
