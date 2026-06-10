<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Request;
use App\Services\AuditLog;

/**
 * Self-service account management for the logged-in user: edit personal
 * information and change the password. Contact identifiers (email / phone)
 * are intentionally read-only here — changing them needs a re-verification
 * flow, which is a separate, later step.
 */
final class ProfileController
{
    public function edit(Request $request): void
    {
        view('profile/edit', [
            'user'      => current_user() ?? [],
            'countries' => config('countries', []),
        ]);
    }

    /** Save the editable profile fields (mirrors the registration validation). */
    public function update(Request $request): void
    {
        $userId = (int) current_user_id();

        $fullName  = input_string('full_name');
        $nickname  = input_string('nickname');
        $birthdate = parse_birthdate_fr((string) input_string('birthdate', ''));
        $gender    = whitelist(strtolower((string) input_string('gender', '')), ['homme', 'femme', 'autre'], null);
        $city      = input_string('city');

        $countries = config('countries', []);
        $country   = strtoupper((string) input_string('country_code', ''));
        $country   = isset($countries[$country]) ? $country : null;

        $errors = [];
        if ($fullName === null)  { $errors['full_name'] = t('validation.required'); }
        if ($nickname === null)  { $errors['nickname']  = t('validation.required'); }
        if ($birthdate === null) { $errors['birthdate'] = t('validation.birthdate_invalid'); }
        if ($gender === null)    { $errors['gender']    = t('validation.required'); }
        if ($country === null)   { $errors['country_code'] = t('validation.required'); }

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/profile');
        }

        User::updateProfile($userId, [
            'full_name'    => $fullName,
            'nickname'     => $nickname,
            'birthdate'    => $birthdate,
            'gender'       => $gender,
            'country_code' => $country,
            'city'         => $city,
        ]);

        AuditLog::record($userId, 'user.profile_updated', 'user', $userId, [], $request->ipBinary());
        clear_old();
        flash('success', t('flash.profile_updated'));
        redirect('/profile');
    }

    /** Change the password after verifying the current one. */
    public function updatePassword(Request $request): void
    {
        $user    = current_user();
        $userId  = (int) current_user_id();
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];
        if ($user === null || !password_verify($current, (string) $user['password_hash'])) {
            $errors['current_password'] = t('validation.current_password_wrong');
        }
        if (!is_valid_password($new)) {
            $errors['password'] = t('validation.password_short', ['min' => config('app.password_min_length', 12)]);
        }
        if ($new !== $confirm) {
            $errors['password_confirm'] = t('validation.password_mismatch');
        }

        if ($errors !== []) {
            set_errors($errors);
            redirect('/profile#password');
        }

        User::updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        AuditLog::record($userId, 'auth.password_changed', 'user', $userId, [], $request->ipBinary());
        flash('success', t('flash.password_changed'));
        redirect('/profile');
    }
}
