<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Avatar;
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
        $user = current_user() ?? [];
        view('profile/edit', [
            'user'           => $user,
            'countries'      => config('countries', []),
            'avatar_version' => Avatar::versionFor((int) ($user['id'] ?? 0)),
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
            redirect($this->accountReturnPath() . '#sec-password');
        }

        User::updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        AuditLog::record($userId, 'auth.password_changed', 'user', $userId, [], $request->ipBinary());
        flash('success', t('flash.password_changed'));
        redirect($this->accountReturnPath());
    }

    /**
     * Langue d'interface + devise d'affichage. Les cookies pilotent la requête
     * (voir bootstrap) ; on persiste aussi en base pour les futurs appareils.
     */
    public function updatePreferences(Request $request): void
    {
        $userId   = (int) current_user_id();
        $locale   = (string) ($_POST['locale'] ?? '');
        $currency = strtoupper((string) ($_POST['currency'] ?? ''));

        if (!in_array($locale, config('app.locales', ['fr', 'en']), true)) {
            $locale = current_locale();
        }
        if (!in_array($currency, config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']), true)) {
            $currency = current_currency();
        }

        $opts = ['expires' => time() + 31536000, 'path' => '/', 'secure' => request_is_https(), 'httponly' => true, 'samesite' => 'Lax'];
        setcookie('locale', $locale, $opts);
        setcookie('currency', $currency, $opts);
        User::updatePreferences($userId, $locale, $currency);

        AuditLog::record($userId, 'user.preferences_updated', 'user', $userId, ['locale' => $locale, 'currency' => $currency], $request->ipBinary());
        flash('success', t('flash.preferences_saved'));
        redirect($this->accountReturnPath());
    }

    /** Les vendeurs gèrent compte/sécurité dans « Réglages » ; les particuliers dans « Profil ». */
    private function accountReturnPath(): string
    {
        return ((current_user()['account_type'] ?? '') === 'professionnel') ? '/vendeur/reglages' : '/profile';
    }

    /* ---- Photo de profil ----------------------------------------- */

    private const AVATAR_SIZE      = 256;              // côté du carré stocké
    private const AVATAR_MAX_BYTES = 4 * 1024 * 1024;  // garde-fou serveur

    /**
     * Sert l'avatar d'un utilisateur par son identifiant public (UUID, non
     * énumérable). Public : les avatars apparaîtront sur les annonces.
     */
    public function avatar(Request $request): void
    {
        $row = Avatar::findByPublicId((string) $request->param('pid', ''));
        if ($row === null) {
            abort(404);
        }
        header('Content-Type: ' . $row['mime']);
        header('Cache-Control: public, max-age=86400');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) strtotime($row['updated_at'])) . ' GMT');
        echo $row['data'];
        exit;
    }

    /** Reçoit la photo, la recadre en carré 256×256 et la stocke en base. */
    public function updatePhoto(Request $request): void
    {
        $userId = (int) current_user_id();
        $file   = $_FILES['photo'] ?? null;
        $error  = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            $this->photoFail('validation.avatar_too_big');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $this->photoFail('validation.avatar_invalid');
        }
        if ((int) $file['size'] > self::AVATAR_MAX_BYTES) {
            $this->photoFail('validation.avatar_too_big');
        }

        $info = @getimagesize((string) $file['tmp_name']);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->photoFail('validation.avatar_invalid');
        }

        $raw = (string) file_get_contents((string) $file['tmp_name']);
        $processed = $this->squareThumbnail($raw, $mime);
        if ($processed === null) {
            // GD indisponible ou image illisible : on accepte l'original seulement
            // s'il est déjà raisonnable (le JS du formulaire réduit en amont).
            if (strlen($raw) > 600 * 1024) {
                $this->photoFail('validation.avatar_too_big');
            }
            $processed = [$mime, $raw];
        }

        Avatar::save($userId, $processed[0], $processed[1]);
        AuditLog::record($userId, 'user.avatar_updated', 'user', $userId, [], $request->ipBinary());
        flash('success', t('flash.avatar_updated'));
        redirect($this->photoReturnPath());
    }

    public function deletePhoto(Request $request): void
    {
        $userId = (int) current_user_id();
        Avatar::delete($userId);
        AuditLog::record($userId, 'user.avatar_deleted', 'user', $userId, [], $request->ipBinary());
        flash('success', t('flash.avatar_deleted'));
        redirect($this->photoReturnPath());
    }

    private function photoFail(string $messageKey): never
    {
        set_errors(['photo' => t($messageKey)]);
        redirect($this->photoReturnPath());
    }

    /**
     * Les vendeurs gèrent leur logo depuis le tableau de bord ; les
     * particuliers gèrent leur photo depuis la page profil.
     */
    private function photoReturnPath(): string
    {
        return ((current_user()['account_type'] ?? '') === 'professionnel') ? '/vendeur/profil' : '/profile';
    }

    /**
     * Recadre au centre en carré et réduit à 256×256 JPEG. Le re-encodage
     * supprime les métadonnées (EXIF/GPS) et neutralise tout contenu piégé.
     * Retourne [mime, blob] ou null si GD est indisponible/illisible.
     *
     * @return array{0:string,1:string}|null
     */
    private function squareThumbnail(string $raw, string $mime): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return null;
        }

        // Les photos de téléphone portent souvent l'orientation en EXIF,
        // que GD ignore : on redresse avant de recadrer.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($raw));
            $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
                3       => imagerotate($src, 180, 0),
                6       => imagerotate($src, -90, 0),
                8       => imagerotate($src, 90, 0),
                default => false,
            };
            if ($rotated !== false) {
                imagedestroy($src);
                $src = $rotated;
            }
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $x = intdiv($w - $side, 2);
        $y = intdiv($h - $side, 2);

        $out = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        // fond blanc pour les PNG/WebP transparents re-encodés en JPEG
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $src, 0, 0, $x, $y, self::AVATAR_SIZE, self::AVATAR_SIZE, $side, $side);

        ob_start();
        imagejpeg($out, null, 85);
        $blob = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($out);

        return $blob === '' ? null : ['image/jpeg', $blob];
    }
}
