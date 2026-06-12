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
     * Step 2 (Particulier): the individual form. The country select is pre-chosen
     * from the IP (country-level is reliable); the city field intentionally starts
     * EMPTY — IP-level city is too often wrong (carrier/VPN exit). The browser's
     * silent GPS refinement fills it only with a precise fix (see app.js).
     */
    public function showRegisterParticulier(Request $request): void
    {
        view('auth/register_particulier', [
            'detected_country' => detect_country_code(),
            'countries'        => config('countries', []),
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
            'password_hash'      => password_hash($password, PASSWORD_DEFAULT),
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

        $user = $identifier !== '' ? User::findByEmailOrPhone($identifier) : null;
        $ok   = $user !== null && password_verify($password, $user['password_hash']);

        LoginAttempt::record($identifier !== '' ? $identifier : null, $request->ipBinary(), $ok);

        if (!$ok) {
            AuditLog::record(null, 'auth.login_failed', 'user', null, ['id' => $identifier], $request->ipBinary());
            keep_old($_POST);
            flash('error', t('flash.invalid_credentials'));
            redirect('/login');
        }

        if (($user['status'] ?? '') !== 'active') {
            flash('error', t('flash.account_suspended'));
            redirect('/login');
        }

        // Upgrade the hash if the algorithm/cost has changed since signup.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        login_user((int) $user['id']); // regenerates session id
        unset($_SESSION['geo']); // réamorce la géo depuis la localisation du compte
        $_SESSION['geo_autoprompt'] = true; // active la position précise après connexion
        AuditLog::record((int) $user['id'], 'auth.login_success', 'user', (int) $user['id'], [], $request->ipBinary());
        clear_old();
        flash('success', t('flash.logged_in'));
        redirect('/dashboard');
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
        view('auth/reset', ['token' => (string) ($_GET['token'] ?? '')]);
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
            redirect('/reset-password?token=' . urlencode($token));
        }

        $userId = PasswordReset::consume($token);
        if ($userId === null) {
            flash('error', t('flash.invalid_token'));
            redirect('/forgot-password');
        }

        User::updatePassword($userId, password_hash($password, PASSWORD_DEFAULT));
        AuditLog::record($userId, 'auth.password_reset', 'user', $userId, [], $request->ipBinary());
        flash('success', t('flash.reset_ok'));
        redirect('/login');
    }

    /* ---- Email verification ------------------------------------- */

    public function verifyEmail(Request $request): void
    {
        $token  = (string) ($_GET['token'] ?? '');
        $userId = EmailVerification::consume($token);

        if ($userId === null) {
            flash('error', t('flash.invalid_token'));
            redirect(auth_check() ? '/dashboard' : '/login');
        }

        AuditLog::record($userId, 'auth.email_verified', 'user', $userId, [], $request->ipBinary());
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
        return '<p>' . e($intro) . '</p>'
            . '<p><a href="' . e($link) . '">' . e($cta) . '</a></p>'
            . '<p style="color:#666;font-size:13px">' . e($link) . '</p>';
    }
}
