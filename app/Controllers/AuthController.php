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

    public function showRegister(Request $request): void
    {
        view('auth/register');
    }

    public function register(Request $request): void
    {
        $email    = input_email('email');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $locale   = whitelist(input_string('locale'), config('app.locales', ['fr', 'en']), current_locale());
        $country  = $this->normaliseCountry(input_string('country'));

        $errors = [];
        if ($email === null) {
            $errors['email'] = t('validation.email_invalid');
        } elseif (User::emailExists($email)) {
            $errors['email'] = t('validation.email_taken');
        }
        if (!is_valid_password($password)) {
            $errors['password'] = t('validation.password_short', ['min' => config('app.password_min_length', 12)]);
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = t('validation.password_mismatch');
        }

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/register');
        }

        $userId = User::create([
            'email'              => $email,
            'password_hash'      => password_hash($password, PASSWORD_DEFAULT),
            'role'               => 'user',
            'locale'             => $locale,
            'country_code'       => $country,
            'preferred_currency' => current_currency(),
            'status'             => 'active',
        ]);

        AuditLog::record($userId, 'user.register', 'user', $userId, [], $request->ipBinary());
        $this->sendVerificationEmail(['id' => $userId, 'email' => $email]);

        login_user($userId);
        clear_old();
        flash('success', t('flash.registered'));
        redirect('/verify-email/notice');
    }

    /* ---- Login / logout ----------------------------------------- */

    public function showLogin(Request $request): void
    {
        view('auth/login');
    }

    public function login(Request $request): void
    {
        $email    = input_email('email');
        $password = (string) ($_POST['password'] ?? '');

        $user = $email !== null ? User::findByEmail($email) : null;
        $ok   = $user !== null && password_verify($password, $user['password_hash']);

        LoginAttempt::record($email, $request->ipBinary(), $ok);

        if (!$ok) {
            AuditLog::record(null, 'auth.login_failed', 'user', null, ['email' => $email], $request->ipBinary());
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
        AuditLog::record((int) $user['id'], 'auth.login_success', 'user', (int) $user['id'], [], $request->ipBinary());
        clear_old();
        flash('success', t('flash.logged_in'));
        redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        $id = current_user_id();
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

    private function normaliseCountry(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[A-Za-z]{2}$/', $value)) {
            return strtoupper($value);
        }
        return null;
    }
}
