<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Services\AccountData;
use App\Services\AccountEraser;
use App\Services\AuditLog;

/**
 * Droits RGPD en libre-service pour la personne connectée :
 *   - Art. 15 / 20 : télécharger toutes ses données (export JSON).
 *   - Art. 17      : supprimer son compte (anonymisation + suppression douce),
 *                    protégé par re-saisie du mot de passe + mot-clé de confirmation.
 */
final class PrivacyController
{
    /** Page « Mes données » : aperçu des droits + export + accès à la suppression. */
    public function data(Request $request): void
    {
        view('privacy/data', ['user' => current_user() ?? []]);
    }

    /** Télécharge l'export JSON de toutes les données personnelles du compte. */
    public function export(Request $request): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        $userId = (int) $user['id'];

        $payload = AccountData::export($userId, $user);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            flash('error', t('privacy.export_error'));
            redirect('/profile/donnees');
        }

        AuditLog::record($userId, 'user.data_exported', 'user', $userId, [], $request->ipBinary());

        $filename = 'afriklink-donnees-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen((string) $json));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo $json;
        exit;
    }

    /** Page de confirmation avant suppression définitive. */
    public function confirmDelete(Request $request): void
    {
        view('privacy/delete', ['user' => current_user() ?? []]);
    }

    /** Exécute l'effacement après double vérification (mot de passe + mot-clé). */
    public function delete(Request $request): void
    {
        $user   = current_user();
        $userId = (int) current_user_id();
        if ($user === null) {
            redirect('/login');
        }

        // Garde-fou : un compte administrateur ne s'auto-supprime pas ici
        // (éviter de se verrouiller hors de la plateforme par mégarde).
        if (($user['role'] ?? 'user') === 'admin') {
            flash('error', t('privacy.delete_admin_blocked'));
            redirect('/profile/donnees');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm'] ?? '');
        $keyword  = t('privacy.delete_keyword');

        $errors = [];
        if (!password_verify($password, (string) $user['password_hash'])) {
            $errors['password'] = t('validation.current_password_wrong');
        }
        if (mb_strtoupper(trim($confirm)) !== mb_strtoupper($keyword)) {
            $errors['confirm'] = t('privacy.delete_keyword_wrong', ['word' => $keyword]);
        }
        if ($errors !== []) {
            set_errors($errors);
            redirect('/profile/supprimer');
        }

        AccountEraser::erase($userId, $user);
        // Journalise l'effacement AVANT de fermer la session (traçabilité).
        AuditLog::record($userId, 'user.account_deleted', 'user', $userId, [], $request->ipBinary());

        logout_user();
        flash('success', t('privacy.delete_done'));
        redirect('/');
    }
}
