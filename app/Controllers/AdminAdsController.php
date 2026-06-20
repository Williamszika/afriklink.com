<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AdCampaign;
use App\Request;
use App\Services\AuditLog;

/**
 * Régie publicitaire (back-office staff) : liste des campagnes « AfrikLink Ads »,
 * revenu cumulé, et modération (suspendre / refuser une campagne).
 */
final class AdminAdsController
{
    public function index(Request $request): void
    {
        view('admin/ads', [
            'page_title'    => t('ads.admin_title'),
            'campaigns'     => AdCampaign::adminList(200),
            'active_count'  => AdCampaign::activeCount(),
            'revenue_cents' => AdCampaign::revenueCents(),
            'revenue_cur'   => AdCampaign::baseCurrency(),
        ]);
    }

    /** Modération : 'stop' (suspend) ou 'reject' (refuse) une campagne. */
    public function action(Request $request): void
    {
        $pid    = (string) $request->param('pid', '');
        $action = whitelist((string) input_string('action', ''), ['stop', 'reject'], null);
        if ($action === null) {
            abort(404);
        }
        $status = $action === 'reject' ? 'rejected' : 'stopped';
        if (!AdCampaign::adminSetStatus($pid, $status)) {
            abort(404);
        }
        AuditLog::record((int) current_user_id(), 'ads.campaign_' . $action, 'ad_campaign', null, ['pid' => $pid], $request->ipBinary());
        flash('success', t('ads.admin_done'));
        redirect('/admin/publicite');
    }
}
