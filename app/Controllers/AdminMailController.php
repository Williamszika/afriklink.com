<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Services\AuditLog;
use App\Services\MailService;

/**
 * Diagnostic e-mail (staff). Affiche la configuration MAIL_* effective (sans
 * jamais révéler la clé) et envoie un e-mail de test pour vérifier que la
 * livraison fonctionne réellement en production.
 */
final class AdminMailController
{
    public function index(Request $request): void
    {
        view('admin/mail', ['cfg' => self::effectiveConfig(), 'me' => trim((string) (current_user()['email'] ?? ''))]);
    }

    /** Envoie un e-mail de test à l'adresse du compte staff connecté. */
    public function sendTest(Request $request): void
    {
        $user = current_user();
        $to   = trim((string) ($user['email'] ?? ''));
        if ($to === '') {
            flash('error', t('admin.mail.no_email'));
            redirect('/admin/email');
        }

        $app  = (string) config('app.name', 'Afriklink');
        $now  = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        // E-mail de test branché sur le gabarit de marque : le staff voit
        // exactement l'identité qu'un client recevra.
        $html = render_partial('emails/base', [
            'subject'   => t('admin.mail.test_subject', ['app' => $app]),
            'preheader' => t('admin.mail.test_body', ['app' => $app]),
            'heading'   => '✅ ' . e(t('admin.mail.test_subject', ['app' => $app])),
            'intro'     => e(t('admin.mail.test_body', ['app' => $app])),
            'body'      => '<p class="afk-link" style="margin:0">' . e($now) . '</p>',
            'accent'    => 'forest',
        ]);
        $ok = MailService::send(
            $to,
            t('admin.mail.test_subject', ['app' => $app]),
            $html,
            t('admin.mail.test_body', ['app' => $app]) . "\n" . $now
        );

        AuditLog::record((int) $user['id'], 'mail.test', 'mail', null, ['to' => $to, 'ok' => $ok], $request->ipBinary());

        if ($ok) {
            flash('success', t('admin.mail.test_ok', ['email' => $to]));
        } else {
            flash('error', t('admin.mail.test_fail', ['err' => MailService::$lastError ?? '—']));
        }
        redirect('/admin/email');
    }

    /**
     * Configuration MAIL_* effective, SANS secret : on n'expose que « définie / non
     * définie » pour la clé, jamais sa valeur.
     *
     * @return array{driver:string,from:string,from_name:string,api_key_set:bool,api_url:string,smtp_host:string,delivers:bool}
     */
    private static function effectiveConfig(): array
    {
        $driver  = (string) ($_ENV['MAIL_DRIVER'] ?? 'log');
        $apiKey  = trim((string) ($_ENV['MAIL_API_KEY'] ?? '')) !== '';
        $smtp    = trim((string) ($_ENV['MAIL_HOST'] ?? '')) !== '';
        $delivers = match ($driver) {
            'api'  => $apiKey,
            'smtp' => $smtp && class_exists(\PHPMailer\PHPMailer\PHPMailer::class),
            default => false, // 'log' = journalisé seulement, pas livré
        };
        return [
            'driver'      => $driver,
            'from'        => (string) ($_ENV['MAIL_FROM'] ?? ''),
            'from_name'   => (string) ($_ENV['MAIL_FROM_NAME'] ?? ''),
            'api_key_set' => $apiKey,
            'api_url'     => (string) ($_ENV['MAIL_API_URL'] ?? 'https://api.brevo.com/v3/smtp/email'),
            'smtp_host'   => (string) ($_ENV['MAIL_HOST'] ?? ''),
            'delivers'    => $delivers,
        ];
    }
}
