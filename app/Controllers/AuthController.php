<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmailVerification;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\MailService;

/**
 * Authentication: registration, login/logout, password reset, email verification.
 * Security posture (security.md §4–6): hashed passwords, session regeneration on
 * privilege change, neutral messages that never reveal whether an account exists,
 * CSRF + rate limiting enforced by middleware (see config/routes.php).
 */
final class AuthController
{
    /* ---- Registration ------------------------------------------- */

    /** Step 1: choose an account type (Particulier / Professionnel). */
    public function showRegisterChoice(Request $request): void
    {
        view('auth/register_choice');
    }

    /**
     * Step 2 (Particulier): the individual form. Country, city and phone dial code
     * are pre-filled and LOCKED from the detected location — detected_geo() gives
     * the best available source (saved account location > precise GPS > IP), and
     * works on ANY host (it falls back to a server-side IP lookup when no CDN geo
     * header is present). The browser's silent GPS refinement sharpens the city,
     * and the "Ce n'est pas ma position ?" link reopens everything (see app.js).
     */
    public function showRegisterParticulier(Request $request): void
    {
        $geo = detected_geo();
        view('auth/register_particulier', [
            'detected_country' => (string) ($geo['country_code'] ?? ''),
            'detected_city'    => (string) ($geo['city'] ?? ''),
            'countries'        => countries_list(),
        ]);
    }

    public function registerParticulier(Request $request): void
    {
        $password  = (string) ($_POST['password'] ?? '');
        $confirm   = (string) ($_POST['password_confirm'] ?? '');
        $fullName  = input_string('full_name');
        $nickname  = input_string('nickname');
        $birthdate = parse_birthdate_fr((string) input_string('birthdate', ''));
        $gender    = whitelist(strtolower((string) input_string('gender', '')), ['homme', 'femme', 'autre'], null);
        $genderOther = $gender === 'autre' ? (mb_substr(trim((string) input_string('gender_other', '')), 0, 40) ?: null) : null;
        $city      = input_string('city');

        $countries = config('countries', []);
        $country   = strtoupper((string) input_string('country_code', ''));
        $country   = isset($countries[$country]) ? $country : null;

        // Identify with EITHER email OR phone.
        $method = whitelist((string) input_string('contact_method', 'email'), ['email', 'phone'], 'email');
        $email  = null;
        $phone  = null;

        $errors = [];
        if ($method === 'email') {
            $email = input_email('email');
            if ($email === null)               { $errors['email'] = t('validation.email_invalid'); }
            elseif (User::emailExists($email)) { $errors['email'] = t('validation.email_taken'); }
        } else {
            $dial     = dial_code(strtoupper((string) input_string('dial_country', '')));
            $national = ltrim((string) preg_replace('/\D+/', '', (string) input_string('phone_number', '')), '0');
            if ($dial === '' || strlen($national) < 6 || strlen($national) > 12) {
                $errors['phone'] = t('validation.phone_invalid');
            } else {
                $phone = '+' . $dial . $national;
                if (User::phoneExists($phone)) { $errors['phone'] = t('validation.phone_taken'); }
            }
        }

        if ($fullName === null)            { $errors['full_name'] = t('validation.required'); }
        if ($nickname === null)            { $errors['nickname'] = t('validation.required'); }
        if (!is_valid_password($password)) { $errors['password'] = t('validation.password_short', ['min' => config('app.password_min_length', 12)]); }
        if ($password !== $confirm)        { $errors['password_confirm'] = t('validation.password_mismatch'); }
        if ($birthdate === null)           { $errors['birthdate'] = t('validation.birthdate_invalid'); }
        if ($gender === null)              { $errors['gender'] = t('validation.required'); }
        if ($country === null)             { $errors['country_code'] = t('validation.required'); }
        if (!\App\Services\Captcha::verify()) { $errors['captcha'] = t('captcha.error'); }

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/register/particulier');
        }

        $userId = User::create([
            'email'              => $email,
            'phone'              => $phone,
            'password_hash'      => password_hash($password, password_algo()),
            'role'               => 'user',
            'account_type'       => 'particulier',
            'full_name'          => $fullName,
            'nickname'           => $nickname,
            'birthdate'          => $birthdate,
            'gender'             => $gender,
            'gender_other'       => $genderOther,
            'country_code'       => $country,
            'city'               => $city,
            'locale'             => current_locale(),
            'preferred_currency' => current_currency(),
            'status'             => 'active',
        ]);

        AuditLog::record($userId, 'user.register', 'user', $userId, ['type' => 'particulier', 'via' => $method], $request->ipBinary());
        login_user($userId);
        clear_old();

        if ($email !== null) {
            // Inscription par e-mail : abonnement automatique à la newsletter.
            \App\Models\NewsletterSubscriber::subscribe($email, current_locale(), 'signup');
            $this->sendVerificationEmail(['id' => $userId, 'email' => $email]);
            flash('success', t('flash.registered'));
            redirect('/verify-email/notice');
        }

        // Phone account: email verification N/A (SMS verification is a future step).
        flash('success', t('flash.registered_phone'));
        redirect('/dashboard');
    }

    /* ---- Login / logout ----------------------------------------- */

    public function showLogin(Request $request): void
    {
        view('auth/login');
    }

    public function login(Request $request): void
    {
        $identifier = (string) (input_string('identifier') ?? '');
        $password   = (string) ($_POST['password'] ?? '');
        // Clé CANONIQUE pour le verrouillage : un compte TÉLÉPHONE saisi avec des
        // espaces/tirets différents doit tomber dans le MÊME compteur (sinon le
        // verrouillage est contournable). E-mail → minuscule ; téléphone → chiffres.
        $lockKey = $identifier === '' ? ''
            : (str_contains($identifier, '@') ? mb_strtolower(trim($identifier)) : normalize_phone($identifier));

        // Verrouillage par COMPTE (en plus de la limite par IP) : après trop
        // d'échecs récents sur CET identifiant, on bloque temporairement — robuste
        // même si l'IP est usurpée. Fenêtre glissante : auto-expire toute seule.
        if ($lockKey !== '') {
            try {
                if (LoginAttempt::recentFailures($lockKey, 900) >= 10) {
                    // Égalise le temps de réponse du chemin « verrouillé » avec un
                    // login normal (un haché est toujours vérifié) — anti-oracle de
                    // chronométrage qui révélerait qu'un compte est verrouillé.
                    password_verify($password, self::dummyHash());
                    AuditLog::record(null, 'auth.login_locked', 'user', null, ['id' => audit_identifier_token($identifier)], $request->ipBinary());
                    keep_old($_POST);
                    flash('error', t('flash.login_locked'));
                    redirect('/login');
                }
            } catch (\Throwable) {
                // Table indisponible : on ne bloque pas (le mot de passe protège).
            }
        }

        $user = $identifier !== '' ? User::findByEmailOrPhone($identifier) : null;
        // Anti-énumération par chronométrage : on exécute TOUJOURS un
        // password_verify, même quand le compte n'existe pas, contre un haché
        // factice de MÊME algorithme/coût. Sans cela, le chemin « compte
        // inconnu » répond plus vite et trahit les e-mails/numéros enregistrés.
        $hash = is_string($user['password_hash'] ?? null) && $user['password_hash'] !== ''
            ? (string) $user['password_hash'] : self::dummyHash();
        $ok   = password_verify($password, $hash) && $user !== null;

        LoginAttempt::record($lockKey !== '' ? $lockKey : null, $request->ipBinary(), $ok);

        if (!$ok) {
            AuditLog::record(null, 'auth.login_failed', 'user', null, ['id' => audit_identifier_token($identifier)], $request->ipBinary());
            keep_old($_POST);
            flash('error', t('flash.invalid_credentials'));
            redirect('/login');
        }

        if (($user['status'] ?? '') !== 'active') {
            // Message GÉNÉRIQUE (identique à un mauvais mot de passe) : on ne révèle
            // pas qu'un mot de passe CORRECT vise un compte suspendu (oracle). Le
            // cas reste journalisé pour le suivi admin.
            AuditLog::record((int) $user['id'], 'auth.login_suspended', 'user', (int) $user['id'], [], $request->ipBinary());
            keep_old($_POST);
            flash('error', t('flash.invalid_credentials'));
            redirect('/login');
        }

        // Upgrade the hash if the algorithm/cost has changed since signup.
        if (password_needs_rehash($user['password_hash'], password_algo())) {
            User::updatePassword((int) $user['id'], password_hash($password, password_algo()));
        }

        login_user((int) $user['id']); // regenerates session id
        unset($_SESSION['geo']); // réamorce la géo depuis la localisation du compte
        $_SESSION['geo_autoprompt'] = true; // active la position précise après connexion
        AuditLog::record((int) $user['id'], 'auth.login_success', 'user', (int) $user['id'], [], $request->ipBinary());
        clear_old();
        // Genre passé explicitement : current_user() a été mémoïsé « invité »
        // par le middleware avant la connexion.
        flash('success', t('flash.logged_in', ['fe' => ($user['gender'] ?? '') === 'femme' ? 'e' : '']));
        // Retour sur la page initialement demandée (ex. la caisse), sinon le tableau de bord.
        $to = (string) ($_SESSION['intended'] ?? '');
        unset($_SESSION['intended']);
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || preg_match('/[\x00-\x1f]/', $to)) {
            $to = '/dashboard';
        }
        redirect($to);
    }

    public function logout(Request $request): void
    {
        $id = current_user_id();
        current_user(); // mémorise le genre avant la fin de session (accord « Déconnecté:fe »)
        AuditLog::record($id, 'auth.logout', 'user', $id, [], $request->ipBinary());
        logout_user();
        flash('success', t('flash.logged_out'));
        redirect('/');
    }

    /* ---- Password reset ----------------------------------------- */

    public function showForgot(Request $request): void
    {
        view('auth/forgot');
    }

    public function sendReset(Request $request): void
    {
        $email = input_email('email');

        if ($email !== null) {
            $user = User::findByEmail($email);
            if ($user !== null) {
                $raw = PasswordReset::issue((int) $user['id'], (int) config('app.password_reset_ttl', 3600));
                $link = url('/reset-password?token=' . $raw);
                MailService::send(
                    $user['email'],
                    t('mail.reset.subject'),
                    $this->emailHtml(t('mail.reset.body', ['app' => config('app.name', 'AfrikaLink')]), $link, t('mail.reset.cta'))
                );
                AuditLog::record((int) $user['id'], 'auth.reset_requested', 'user', (int) $user['id'], [], $request->ipBinary());
            }
        }

        // Neutral response — never reveal whether the address is registered (security.md §4).
        flash('success', t('flash.reset_sent'));
        redirect('/login');
    }

    public function showReset(Request $request): void
    {
        // On SORT le jeton de l'URL dès l'arrivée. S'il est présent en query
        // (lien e-mail), on le mémorise en session puis on redirige vers une URL
        // PROPRE (sans jeton) : il disparaît aussitôt de la barre d'adresse, de
        // l'historique et de tout Referer. Le formulaire le repose ensuite en
        // champ caché (corps de la requête POST, jamais journalisé).
        $qtoken = (string) ($_GET['token'] ?? '');
        if ($qtoken !== '') {
            $_SESSION['pw_reset_token'] = $qtoken;
            redirect('/reset-password');
        }
        no_store_secret_headers();
        view('auth/reset', ['token' => (string) ($_SESSION['pw_reset_token'] ?? '')]);
    }

    public function reset(Request $request): void
    {
        $token    = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];
        if (!is_valid_password($password)) {
            $errors['password'] = t('validation.password_short', ['min' => config('app.password_min_length', 12)]);
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = t('validation.password_mismatch');
        }
        if ($errors !== []) {
            set_errors($errors);
            // On garde le jeton en SESSION (pas dans l'URL) pour le réessai.
            $_SESSION['pw_reset_token'] = $token;
            redirect('/reset-password');
        }

        $userId = PasswordReset::consume($token);
        if ($userId === null) {
            unset($_SESSION['pw_reset_token']);
            flash('error', t('flash.invalid_token'));
            redirect('/forgot-password');
        }
        unset($_SESSION['pw_reset_token']);

        User::updatePassword($userId, password_hash($password, password_algo()));
        // Toute session encore ouverte avec l'ancien mot de passe est invalidée
        // (un attaquant qui détenait une session ne reste pas connecté).
        User::bumpSessionEpoch($userId);
        AuditLog::record($userId, 'auth.password_reset', 'user', $userId, [], $request->ipBinary());
        flash('success', t('flash.reset_ok'));
        redirect('/login');
    }

    /* ---- Email verification ------------------------------------- */

    public function verifyEmail(Request $request): void
    {
        // Jeton à usage unique consommé immédiatement : pas de cache ni de Referer.
        no_store_secret_headers();
        $token  = (string) ($_GET['token'] ?? '');
        $userId = EmailVerification::consume($token);

        if ($userId === null) {
            flash('error', t('flash.invalid_token'));
            redirect(auth_check() ? '/dashboard' : '/login');
        }

        AuditLog::record($userId, 'auth.email_verified', 'user', $userId, [], $request->ipBinary());
        // E-mail de bienvenue (compte activé) — best-effort.
        $u = User::findById($userId);
        if ($u !== null && !empty($u['email'])) {
            $name    = trim((string) ($u['full_name'] ?? '')) ?: trim((string) ($u['nickname'] ?? ''));
            $heading = $name !== '' ? t('mail.welcome.heading', ['name' => $name]) : t('mail.welcome.subject');
            try {
                MailService::send((string) $u['email'], t('mail.welcome.subject'), render_partial('emails/base', [
                    'subject'   => t('mail.welcome.subject'),
                    'preheader' => t('mail.welcome.intro'),
                    'heading'   => e($heading),
                    'intro'     => e(t('mail.welcome.intro')),
                    'cta_url'   => url('/explorer'),
                    'cta_label' => t('mail.welcome.cta'),
                    'accent'    => 'gold',
                ]));
            } catch (\Throwable) {
            }
        }
        flash('success', t('flash.verify_ok'));
        redirect(auth_check() ? '/dashboard' : '/login');
    }

    public function verifyNotice(Request $request): void
    {
        view('auth/verify_notice', ['user' => current_user()]);
    }

    public function resendVerification(Request $request): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if (User::isEmailVerified($user)) {
            flash('info', t('flash.already_verified'));
            redirect('/dashboard');
        }

        $this->sendVerificationEmail($user);
        flash('success', t('flash.verify_sent'));
        redirect('/verify-email/notice');
    }

    /* ---- Helpers ------------------------------------------------ */

    /**
     * Haché « leurre » de même algorithme/coût que les vrais, calculé une seule
     * fois par processus. Sert à équilibrer le temps de réponse de la connexion
     * quand le compte n'existe pas (anti-énumération par chronométrage).
     */
    private static function dummyHash(): string
    {
        static $h = null;
        if ($h === null) {
            $h = password_hash('afriklink-timing-equalizer', password_algo());
        }
        return $h;
    }

    private function sendVerificationEmail(array $user): void
    {
        $raw  = EmailVerification::issue((int) $user['id'], (int) config('app.email_verification_ttl', 86400));
        $link = url('/verify-email?token=' . $raw);
        MailService::send(
            $user['email'],
            t('mail.verify.subject'),
            $this->emailHtml(t('mail.verify.body', ['app' => config('app.name', 'AfrikaLink')]), $link, t('mail.verify.cta'))
        );
    }

    private function emailHtml(string $intro, string $link, string $cta): string
    {
        return render_partial('emails/base', [
            'subject'   => t('mail.verify.subject'),
            'preheader' => $intro,
            'heading'   => t('mail.verify.heading'),
            'intro'     => e($intro),
            'cta_url'   => $link,
            'cta_label' => $cta,
            'accent'    => 'forest',
            'outro'     => e(t('mail.verify.outro')),
        ]);
    }
}
