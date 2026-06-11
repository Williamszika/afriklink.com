<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmailVerification;
use App\Models\ProProfile;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\MailService;

/**
 * Inscription VENDEUR — volontairement simple : nom commercial, responsable,
 * e-mail (identifiant), téléphone, pays/ville, mot de passe. Tout le reste
 * (statut juridique, numéro d'enregistrement, adresse, site, langues…) se
 * complète ensuite dans le tableau de bord vendeur (/vendeur/profil).
 */
final class ProRegistrationController
{
    public function show(Request $request): void
    {
        view('auth/register_pro', [
            'detected_country' => detect_country_code(),
            'countries'        => config('countries', []),
        ]);
    }

    public function submit(Request $request): void
    {
        $errors = [];
        $max = (int) config('pro.company_max', 150);

        $companyName = input_string('company_name');
        if ($companyName === null || mb_strlen($companyName) < 2 || mb_strlen($companyName) > $max) {
            $errors['company_name'] = t('validation.company_name', ['max' => $max]);
        }

        $fullName = input_string('full_name');
        if ($fullName === null || mb_strlen($fullName) > 150) {
            $errors['full_name'] = t('validation.required');
        }

        $email = input_email('email');
        if ($email === null) {
            $errors['email'] = t('validation.email_invalid');
        } elseif (User::emailExists($email)) {
            $errors['email'] = t('validation.email_taken');
        }

        $dial     = dial_code(strtoupper((string) input_string('dial_country', '')));
        $national = ltrim((string) preg_replace('/\D+/', '', (string) input_string('phone_number', '')), '0');
        $phone    = null;
        if ($dial === '' || strlen($national) < 6 || strlen($national) > 12) {
            $errors['phone'] = t('validation.phone_invalid');
        } else {
            $phone = '+' . $dial . $national;
            if (User::phoneExists($phone)) {
                $errors['phone'] = t('validation.phone_taken');
            }
        }

        $countries = config('countries', []);
        $country   = strtoupper((string) input_string('country_code', ''));
        if (!isset($countries[$country])) {
            $errors['country_code'] = t('validation.required');
            $country = null;
        }

        $city = input_string('city');
        if ($city !== null && mb_strlen($city) > 120) {
            $city = mb_substr($city, 0, 120);
        }

        $password = (string) ($_POST['password'] ?? '');
        if (!is_valid_password($password)) {
            $errors['password'] = t('validation.password_short', ['min' => config('app.password_min_length', 12)]);
        }
        if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
            $errors['password_confirm'] = t('validation.password_mismatch');
        }

        if ((string) ($_POST['accept_legal'] ?? '') !== '1') {
            $errors['accept_legal'] = t('validation.must_accept');
        }

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/register/vendeur');
        }

        $userId = User::create([
            'email'              => $email,
            'phone'              => $phone,
            'password_hash'      => password_hash($password, PASSWORD_DEFAULT),
            'role'               => 'vendor',
            'account_type'       => 'professionnel', // valeur exacte de l'ENUM users.account_type
            'full_name'          => $fullName,
            'nickname'           => null,
            'birthdate'          => null,
            'gender'             => null,
            'country_code'       => $country,
            'city'               => $city,
            'locale'             => current_locale(),
            'preferred_currency' => current_currency(),
            'status'             => 'active',
        ]);

        ProProfile::create($userId, ['company_name' => $companyName]);

        AuditLog::record($userId, 'user.register', 'user', $userId, ['type' => 'vendeur'], $request->ipBinary());
        clear_old();
        login_user($userId);

        $raw  = EmailVerification::issue($userId, (int) config('app.email_verification_ttl', 86400));
        $link = url('/verify-email?token=' . $raw);
        MailService::send(
            (string) $email,
            t('mail.verify.subject'),
            '<p>' . e(t('mail.verify.body', ['app' => config('app.name', 'AfrikaLink')])) . '</p>'
            . '<p><a href="' . e($link) . '">' . e(t('mail.verify.cta')) . '</a></p>'
            . '<p style="color:#666;font-size:13px">' . e($link) . '</p>'
        );

        flash('success', t('flash.registered_pro'));
        redirect('/verify-email/notice');
    }
}
