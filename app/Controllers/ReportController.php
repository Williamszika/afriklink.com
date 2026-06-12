<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\StorefrontAlert;

/**
 * Signalements de sécurité. /signaler-vitrine : le destinataire de l'e-mail
 * « vitrine créée » clique « ce n'était pas moi » — les administrateurs sont
 * prévenus immédiatement. Fonctionne sans être connecté (jeton à usage
 * unique dans le lien).
 */
final class ReportController
{
    public function storefront(Request $request): void
    {
        $row = StorefrontAlert::consume((string) ($_GET['token'] ?? ''));

        if ($row === null) {
            view('report_done', ['ok' => false, 'page_title' => t('report.title_invalid')]);
            return;
        }

        $user = User::findById((int) $row['user_id']) ?? [];
        AuditLog::record(
            (int) $row['user_id'],
            'storefront.reported_not_me',
            'user',
            (int) $row['user_id'],
            ['type' => $row['vitrine_type'], 'name' => $row['vitrine_name']],
            $request->ipBinary()
        );
        try {
            StorefrontAlert::notifyAdmins($row, $user, (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''));
        } catch (\Throwable) {
            // l'accusé de réception reste affiché même si l'e-mail admin échoue
        }

        view('report_done', ['ok' => true, 'page_title' => t('report.title_ok')]);
    }
}
