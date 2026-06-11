<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProProfile;
use App\Request;
use App\Services\AuditLog;

/**
 * Profil vendeur (tableau de bord) — tout ce que l'inscription simple n'a pas
 * demandé : statut juridique, n° d'enregistrement (→ badge « Vendeur
 * vérifié »), TVA, description, adresse, site web, langues, WhatsApp.
 */
final class SellerProfileController
{
    public function edit(Request $request): void
    {
        $user = $this->sellerOrRedirect();
        view('vendeur/profil', ['active' => 'profil'] + SellerController::commonData($user));
    }

    public function update(Request $request): void
    {
        $user   = $this->sellerOrRedirect();
        $userId = (int) $user['id'];
        $errors = [];
        $max    = (int) config('pro.company_max', 150);

        $companyName = input_string('company_name');
        if ($companyName === null || mb_strlen($companyName) < 2 || mb_strlen($companyName) > $max) {
            $errors['company_name'] = t('validation.company_name', ['max' => $max]);
        }

        $legalForm = input_string('legal_form');
        $legalForm = $legalForm === null ? null : whitelist($legalForm, config('pro.legal_forms', []), null);

        $legalName = input_string('legal_name');
        if ($legalName !== null && mb_strlen($legalName) > $max) {
            $errors['legal_name'] = t('validation.too_long', ['max' => $max]);
        }

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

        $address = input_string('address');
        if ($address !== null && mb_strlen($address) > 220) {
            $errors['address'] = t('validation.too_long', ['max' => 220]);
        }

        $website = input_string('website');
        if ($website !== null) {
            if (!preg_match('#^https?://#i', $website)) {
                $website = 'https://' . $website;
            }
            if (mb_strlen($website) > 200 || filter_var($website, FILTER_VALIDATE_URL) === false) {
                $errors['website'] = t('validation.website_invalid');
            }
        }

        $languages = implode(',', array_values(array_intersect(
            (array) ($_POST['languages'] ?? []),
            config('pro.languages', [])
        )));

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/vendeur/profil');
        }

        ProProfile::update($userId, [
            'company_name'   => $companyName,
            'legal_name'     => $legalName,
            'legal_form'     => $legalForm,
            'reg_number'     => $regNumber,
            'vat_number'     => $vat,
            'description'    => $description,
            'address'        => $address,
            'website'        => $website,
            'languages'      => $languages !== '' ? $languages : null,
            'whatsapp_optin' => (string) ($_POST['whatsapp_optin'] ?? '') === '1',
        ]);

        AuditLog::record($userId, 'seller.profile_updated', 'pro_profile', $userId, [], $request->ipBinary());
        clear_old();
        flash('success', t('flash.seller_profile_saved'));
        redirect('/vendeur/profil');
    }

    /** Réservé aux comptes vendeurs ; les autres retournent au tableau de bord. */
    private function sellerOrRedirect(): array
    {
        $user = current_user() ?? [];
        if (($user['account_type'] ?? '') !== 'professionnel') {
            redirect('/dashboard');
        }
        return $user;
    }
}
