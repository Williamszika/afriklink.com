<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Kyc;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;

/**
 * Vérification d'identité — côté vendeur. Affiche les 3 niveaux successifs et
 * reçoit les soumissions. Les pièces arrivent déjà sur Cloudinary (envoi privé
 * signé) ; ici on RE-VÉRIFIE chacune côté serveur avant de l'enregistrer.
 */
final class KycController
{
    public function submit(Request $request): void
    {
        $userId = (int) current_user_id();
        $level  = (int) $request->param('level', 0);
        $levels = config('kyc.levels', []);

        if (!isset($levels[$level])) {
            abort(404);
        }
        if (!Kyc::isUnlocked($userId, $level)) {
            flash('error', t('kyc.locked_flash'));
            redirect('/vendeur/verification');
        }
        if (!CloudinaryService::configured()) {
            flash('error', t('listing.media_unconfigured'));
            redirect('/vendeur/verification');
        }

        $cfg     = $levels[$level];
        $docType = null;
        if (!empty($cfg['has_doc_type'])) {
            $docType = whitelist((string) input_string('doc_type', ''), config('kyc.id_types', []), null);
            if ($docType === null) {
                set_errors(['kyc' => t('validation.required')]);
                redirect('/vendeur/verification');
            }
        }

        // Pièces transmises par le JS : [{slot, public_id, version, format}, …]
        $raw = json_decode((string) ($_POST['docs_json'] ?? '[]'), true);
        $bySlot = [];
        if (is_array($raw)) {
            foreach ($raw as $d) {
                if (is_array($d) && isset($d['slot'], $d['public_id'])) {
                    $bySlot[(string) $d['slot']] = $d;
                }
            }
        }

        // Vérité serveur : chaque pièce requise doit exister, dans le dossier KYC
        // de CET utilisateur, en mode privé (authenticated).
        $docs = [];
        foreach ($cfg['slots'] as $slot => $required) {
            $d = $bySlot[$slot] ?? null;
            if ($d === null) {
                if ($required) {
                    set_errors(['kyc' => t('kyc.missing_doc')]);
                    redirect('/vendeur/verification');
                }
                continue;
            }
            $meta = CloudinaryService::verifyKycAsset($userId, (string) $d['public_id']);
            if ($meta === null) {
                set_errors(['kyc' => t('kyc.doc_invalid')]);
                redirect('/vendeur/verification');
            }
            $docs[] = [
                'slot'      => $slot,
                'public_id' => (string) $d['public_id'],
                'version'   => $meta['version'],
                'format'    => $meta['format'] !== '' ? $meta['format'] : 'jpg',
            ];
        }

        if ($docs === []) {
            set_errors(['kyc' => t('kyc.missing_doc')]);
            redirect('/vendeur/verification');
        }

        Kyc::submit($userId, $level, $docType, $docs);
        AuditLog::record($userId, 'kyc.submitted', 'kyc', $level, ['level' => $level], $request->ipBinary());
        flash('success', t('kyc.submitted_flash'));
        redirect('/vendeur/verification');
    }
}
