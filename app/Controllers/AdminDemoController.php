<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Services\AuditLog;
use App\Services\DemoSeeder;

/**
 * Données de DÉMONSTRATION (staff) : peupler / retirer 50 boutiques de démo
 * depuis le navigateur, sans accès terminal (utile en hébergement serverless).
 * Réservé au staff (admin_emails) + CSRF + confirmation saisie. Les actions sont
 * journalisées. La démo est VISIBLE PUBLIQUEMENT : retirable à tout moment.
 */
final class AdminDemoController
{
    public function index(Request $request): void
    {
        view('admin/demo', [
            'page_title' => t('admin.demo.title'),
            'count'      => DemoSeeder::count(),
        ]);
    }

    /** Peuple les 50 boutiques de démo. Confirmation « OUI » exigée. */
    public function seed(Request $request): void
    {
        if (mb_strtoupper(trim((string) input_string('confirm', ''))) !== 'OUI') {
            flash('error', t('admin.demo.need_confirm'));
            redirect('/admin/demo');
        }
        try {
            $r = DemoSeeder::seed();
        } catch (\Throwable $e) {
            log_message('error', 'demo.seed failed: ' . $e->getMessage());
            flash('error', t('admin.demo.error', ['msg' => mb_substr($e->getMessage(), 0, 160)]));
            redirect('/admin/demo');
        }
        AuditLog::record((int) (current_user_id() ?? 0), 'demo.seed', 'system', null, $r, $request->ipBinary());
        flash('success', t('admin.demo.seeded', ['n' => $r['boutiques'], 'p' => $r['products']]));
        redirect('/admin/demo');
    }

    /** Retire toutes les données de démo. */
    public function purge(Request $request): void
    {
        try {
            $n = DemoSeeder::purge();
        } catch (\Throwable $e) {
            log_message('error', 'demo.purge failed: ' . $e->getMessage());
            flash('error', t('admin.demo.error', ['msg' => mb_substr($e->getMessage(), 0, 160)]));
            redirect('/admin/demo');
        }
        AuditLog::record((int) (current_user_id() ?? 0), 'demo.purge', 'system', null, ['sellers' => $n], $request->ipBinary());
        flash('success', t('admin.demo.purged', ['n' => $n]));
        redirect('/admin/demo');
    }
}
