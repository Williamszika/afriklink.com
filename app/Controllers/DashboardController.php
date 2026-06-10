<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

final class DashboardController
{
    public function index(Request $request): void
    {
        $user = current_user() ?? [];
        [$completion, $missing] = $this->profileCompletion($user);

        view('dashboard', [
            'user'       => $user,
            'completion' => $completion,
            'missing'    => $missing,
        ]);
    }

    /**
     * Profile completion: share of filled profile fields, plus a verified contact
     * (a phone, or a verified e-mail). Returns [percent, list of missing i18n keys].
     *
     * @return array{0:int,1:list<string>}
     */
    private function profileCompletion(array $u): array
    {
        $contactOk = !empty($u['phone']) || !empty($u['email_verified_at']);
        $checks = [
            'field.full_name'        => !empty($u['full_name']),
            'field.nickname'         => !empty($u['nickname']),
            'field.country'          => !empty($u['country_code']),
            'field.city'             => !empty($u['city']),
            'field.birthdate'        => !empty($u['birthdate']),
            'field.gender'           => !empty($u['gender']),
            'dash.contact_verified'  => $contactOk,
        ];

        $done = count(array_filter($checks));
        $missing = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));

        return [(int) round($done * 100 / count($checks)), $missing];
    }
}
