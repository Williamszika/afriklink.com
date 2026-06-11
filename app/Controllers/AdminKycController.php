<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Kyc;
use App\Models\ProProfile;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;

/**
 * Espace de modération KYC (rôle admin/modérateur). File des demandes en
 * attente, consultation des pièces (servies en flux par notre serveur, jamais
 * d'URL Cloudinary exposée au navigateur), approbation/refus motivé.
 */
final class AdminKycController
{
    public function index(Request $request): void
    {
        Kyc::ensureTables();
        view('admin/kyc_list', ['queue' => Kyc::pendingQueue()]);
    }

    public function show(Request $request): void
    {
        $sub = Kyc::findSubmission((int) $request->param('id', 0));
        if ($sub === null) {
            abort(404);
        }
        view('admin/kyc_show', [
            'sub'  => $sub,
            'docs' => Kyc::documents((int) $sub['id']),
        ]);
    }

    /** Flux binaire d'une pièce — accès relecteur uniquement (StaffMiddleware). */
    public function document(Request $request): void
    {
        $doc = Kyc::findDocument((int) $request->param('id', 0));
        if ($doc === null) {
            abort(404);
        }
        $fetched = CloudinaryService::fetchKycBytes(
            (string) $doc['cloud_public_id'],
            (int) $doc['cloud_version'],
            (string) $doc['cloud_format']
        );
        if ($fetched === null) {
            abort(404);
        }
        AuditLog::record((int) current_user_id(), 'kyc.doc_viewed', 'kyc_doc', (int) $doc['id'], [], $request->ipBinary());

        header('Content-Type: ' . $fetched['content_type']);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');
        echo $fetched['bytes'];
        exit;
    }

    public function review(Request $request): void
    {
        $sub = Kyc::findSubmission((int) $request->param('id', 0));
        if ($sub === null) {
            abort(404);
        }
        $action = whitelist((string) input_string('action', ''), ['approve', 'reject'], null);
        if ($action === null) {
            abort(404);
        }
        $note    = input_string('note');
        $approve = $action === 'approve';
        if (!$approve && ($note === null || mb_strlen($note) < 3)) {
            set_errors(['note' => t('kyc.reject_needs_note')]);
            redirect('/admin/kyc/' . $sub['id']);
        }
        if ($note !== null && mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        Kyc::review((int) $sub['id'], (int) current_user_id(), $approve, $note);

        // Niveau 3 approuvé = identité personnelle pleinement vérifiée.
        if ($approve && (int) $sub['level'] === 3) {
            ProProfile::setVerificationStatus((int) $sub['user_id'], 'verified');
        }

        AuditLog::record(
            (int) current_user_id(),
            'kyc.' . ($approve ? 'approved' : 'rejected'),
            'kyc',
            (int) $sub['id'],
            ['user_id' => (int) $sub['user_id'], 'level' => (int) $sub['level']],
            $request->ipBinary()
        );
        flash('success', t($approve ? 'kyc.approved_flash' : 'kyc.rejected_flash'));
        redirect('/admin/kyc');
    }
}
