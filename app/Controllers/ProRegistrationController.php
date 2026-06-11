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
 * Inscription professionnelle — assistant en 3 étapes, côté serveur (fonctionne
 * sans JavaScript) : 1. Entreprise · 2. Contact & localisation · 3. Responsable
 * & compte (avec récapitulatif). Les saisies vivent en session (pro_draft) et
 * RIEN n'est écrit en base avant la validation finale — aucune entreprise à
 * moitié créée. Le type d'activité (boutique, restaurant…) n'est pas demandé
 * ici : il se choisit ensuite, depuis le tableau de bord pro.
 */
final class ProRegistrationController
{
    private const DRAFT = 'pro_draft';

    public function show(Request $request): void
    {
        $step = $this->clampStep((int) ($_GET['etape'] ?? 1));
        view('auth/register_pro', [
            'step'             => $step,
            'draft'            => $_SESSION[self::DRAFT] ?? [],
            'detected_country' => detect_country_code(),
            'countries'        => config('countries', []),
        ]);
    }

    public function submit(Request $request): void
    {
        $step = $this->clampStep((int) input_string('etape', '1'));

        [$data, $errors] = match ($step) {
            1 => $this->validateStep1(),
            2 => $this->validateStep2(),
            3 => $this->validateStep3(),
        };

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/register/professionnel?etape=' . $step);
        }

        if ($step < 3) {
            $_SESSION[self::DRAFT]['step' . $step] = $data;
            clear_old();
            redirect('/register/professionnel?etape=' . ($step + 1));
        }

        $this->finalize($request, $data);
    }

    /* ---- Étape 1 : l'entreprise ----------------------------------- */

    private function validateStep1(): array
    {
        $errors = [];
        $max = (int) config('pro.company_max', 150);

        $companyName = input_string('company_name');
        if ($companyName === null || mb_strlen($companyName) < 2 || mb_strlen($companyName) > $max) {
            $errors['company_name'] = t('validation.company_name', ['max' => $max]);
        }

        $legalForm = whitelist((string) input_string('legal_form', ''), config('pro.legal_forms', []), null);
        if ($legalForm === null) {
            $errors['legal_form'] = t('validation.required');
        }

        $legalName = input_string('legal_name');
        if ($legalName !== null && mb_strlen($legalName) > $max) {
            $errors['legal_name'] = t('validation.too_long', ['max' => $max]);
        }

        // Numéro d'enregistrement : optionnel (badge « Pro vérifié » après contrôle).
        $regNumber = input_string('reg_number');
        if ($regNumber !== null) {
            $regNumber = preg_replace('/[^A-Za-z0-9 .\/-]/', '', $regNumber) ?: null;
            if ($regNumber !== null && mb_strlen($regNumber) > 64) {
                $errors['reg_number'] = t('validation.too_long', ['max' => 64]);
            }
        }

        $vat = input_string('vat_number');
        if ($vat !== null) {
            $vat = preg_replace('/[^A-Za-z0-9 .-]/', '', $vat) ?: null;
            if ($vat !== null && mb_strlen($vat) > 32) {
                $errors['vat_number'] = t('validation.too_long', ['max' => 32]);
            }
        }

        $descMax = (int) config('pro.description_max', 500);
        $description = input_string('description');
        if ($description !== null && mb_strlen($description) > $descMax) {
            $errors['description'] = t('validation.too_long', ['max' => $descMax]);
        }

        return [[
            'company_name' => $companyName,
            'legal_form'   => $legalForm,
            'legal_name'   => $legalName,
            'reg_number'   => $regNumber,
            'vat_number'   => $vat,
            'description'  => $description,
        ], $errors];
    }

    /* ---- Étape 2 : contact & localisation -------------------------- */

    private function validateStep2(): array
    {
        $errors = [];
        $countries = config('countries', []);

        $country = strtoupper((string) input_string('country_code', ''));
        if (!isset($countries[$country])) {
            $errors['country_code'] = t('validation.required');
            $country = null;
        }

        $city = input_string('city');
        if ($city === null || mb_strlen($city) > 120) {
            $errors['city'] = t('validation.required');
        }

        $address = input_string('address');
        if ($address === null || mb_strlen($address) < 5 || mb_strlen($address) > 220) {
            $errors['address'] = t('validation.address_invalid');
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

        // Site web / page réseau social : on tolère l'absence de « https:// ».
        $website = input_string('website');
        if ($website !== null) {
            if (!preg_match('#^https?://#i', $website)) {
                $website = 'https://' . $website;
            }
            if (mb_strlen($website) > 200 || filter_var($website, FILTER_VALIDATE_URL) === false) {
                $errors['website'] = t('validation.website_invalid');
            }
        }

        $languages = array_values(array_intersect(
            (array) ($_POST['languages'] ?? []),
            config('pro.languages', [])
        ));

        return [[
            'country_code'   => $country,
            'city'           => $city,
            'address'        => $address,
            'email'          => $email,
            'phone'          => $phone,
            'dial_country'   => strtoupper((string) input_string('dial_country', '')),
            'phone_national' => $national,
            'whatsapp_optin' => (string) ($_POST['whatsapp_optin'] ?? '') === '1',
            'website'        => $website,
            'languages'      => implode(',', $languages),
        ], $errors];
    }

    /* ---- Étape 3 : responsable & compte ----------------------------- */

    private function validateStep3(): array
    {
        $errors = [];

        $fullName = input_string('full_name');
        if ($fullName === null || mb_strlen($fullName) > 150) {
            $errors['full_name'] = t('validation.required');
        }

        $password = (string) ($_POST['password'] ?? '');
        if (!is_valid_password($password)) {
            $errors['password'] = t('validation.password_short', ['min' => config('app.password_min_length', 12)]);
        }
        if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
            $errors['password_confirm'] = t('validation.password_mismatch');
        }

        if ((string) ($_POST['accept_terms'] ?? '') !== '1') {
            $errors['accept_terms'] = t('validation.must_accept');
        }
        if ((string) ($_POST['accept_privacy'] ?? '') !== '1') {
            $errors['accept_privacy'] = t('validation.must_accept');
        }

        return [[
            'full_name' => $fullName,
            'password'  => $password,
        ], $errors];
    }

    /* ---- Création finale (transactionnelle) ------------------------- */

    private function finalize(Request $request, array $step3): void
    {
        $draft = $_SESSION[self::DRAFT] ?? [];
        $s1 = $draft['step1'] ?? null;
        $s2 = $draft['step2'] ?? null;
        if ($s1 === null || $s2 === null) {
            redirect('/register/professionnel?etape=1');
        }

        // Re-contrôle d'unicité au moment T (l'e-mail/le numéro a pu être pris
        // entre l'étape 2 et la validation).
        if (User::emailExists($s2['email'])) {
            set_errors(['recap' => t('validation.email_taken')]);
            redirect('/register/professionnel?etape=2');
        }
        if ($s2['phone'] !== null && User::phoneExists($s2['phone'])) {
            set_errors(['recap' => t('validation.phone_taken')]);
            redirect('/register/professionnel?etape=2');
        }

        $userId = User::create([
            'email'              => $s2['email'],
            'phone'              => $s2['phone'],
            'password_hash'      => password_hash($step3['password'], PASSWORD_DEFAULT),
            'role'               => 'vendor',
            'account_type'       => 'professionnel', // valeur exacte de l'ENUM users.account_type
            'full_name'          => $step3['full_name'],
            'nickname'           => null,
            'birthdate'          => null,
            'gender'             => null,
            'country_code'       => $s2['country_code'],
            'city'               => $s2['city'],
            'locale'             => current_locale(),
            'preferred_currency' => current_currency(),
            'status'             => 'active',
        ]);

        ProProfile::create($userId, [
            'company_name'   => $s1['company_name'],
            'legal_name'     => $s1['legal_name'],
            'legal_form'     => $s1['legal_form'],
            'reg_number'     => $s1['reg_number'],
            'vat_number'     => $s1['vat_number'],
            'description'    => $s1['description'],
            'address'        => $s2['address'],
            'website'        => $s2['website'],
            'languages'      => $s2['languages'],
            'whatsapp_optin' => $s2['whatsapp_optin'],
        ]);

        AuditLog::record($userId, 'user.register', 'user', $userId, ['type' => 'pro'], $request->ipBinary());
        unset($_SESSION[self::DRAFT]);
        clear_old();
        login_user($userId);

        $raw  = EmailVerification::issue($userId, (int) config('app.email_verification_ttl', 86400));
        $link = url('/verify-email?token=' . $raw);
        MailService::send(
            $s2['email'],
            t('mail.verify.subject'),
            '<p>' . e(t('mail.verify.body', ['app' => config('app.name', 'AfrikaLink')])) . '</p>'
            . '<p><a href="' . e($link) . '">' . e(t('mail.verify.cta')) . '</a></p>'
            . '<p style="color:#666;font-size:13px">' . e($link) . '</p>'
        );

        flash('success', t('flash.registered_pro'));
        redirect('/verify-email/notice');
    }

    private function clampStep(int $step): int
    {
        $draft = $_SESSION[self::DRAFT] ?? [];
        $reachable = 1;
        if (isset($draft['step1'])) { $reachable = 2; }
        if (isset($draft['step1'], $draft['step2'])) { $reachable = 3; }
        return max(1, min($step, $reachable));
    }
}
