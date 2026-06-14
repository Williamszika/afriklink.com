<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserAddress;
use App\Request;

/**
 * Carnet d'adresses de l'acheteur (/mes-adresses) : pré-remplit la caisse et
 * évite de ressaisir l'adresse à chaque commande.
 */
final class AddressController
{
    public function index(Request $request): void
    {
        $uid = (int) current_user_id();
        view('addresses', [
            'addresses'  => UserAddress::forUser($uid),
            'countries'  => config('countries', []),
            'page_title' => t('addr.title'),
        ]);
    }

    public function store(Request $request): void
    {
        $uid = (int) current_user_id();
        $errors = [];
        $name = trim((string) input_string('recipient_name', ''));
        $line1 = trim((string) input_string('line1', ''));
        $city  = trim((string) input_string('city', ''));
        $cc    = whitelist(strtoupper((string) input_string('country_code', '')), array_keys(config('countries', [])), null);
        if (mb_strlen($name) < 2) {
            $errors['recipient_name'] = t('addr.err_name');
        }
        if (mb_strlen($line1) < 3) {
            $errors['line1'] = t('addr.err_line1');
        }
        if (mb_strlen($city) < 2) {
            $errors['city'] = t('addr.err_city');
        }
        if ($cc === null) {
            $errors['country_code'] = t('addr.err_country');
        }
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/mes-adresses');
        }
        UserAddress::create($uid, [
            'label'        => input_string('label', ''),
            'recipient_name' => $name,
            'line1'        => $line1,
            'line2'        => input_string('line2', ''),
            'city'         => $city,
            'region'       => input_string('region', ''),
            'postal_code'  => input_string('postal_code', ''),
            'country_code' => $cc,
            'phone'        => input_string('phone', ''),
            'is_default'   => (string) input_string('is_default', '') !== '',
        ]);
        clear_old();
        flash('success', t('addr.saved'));
        redirect('/mes-adresses');
    }

    public function setDefault(Request $request): void
    {
        $uid = (int) current_user_id();
        UserAddress::setDefault((int) $request->param('id', '0'), $uid);
        flash('success', t('addr.default_set'));
        redirect('/mes-adresses');
    }

    public function delete(Request $request): void
    {
        $uid = (int) current_user_id();
        UserAddress::delete((int) $request->param('id', '0'), $uid);
        flash('success', t('addr.deleted'));
        redirect('/mes-adresses');
    }
}
