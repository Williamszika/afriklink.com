<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Services\AuditLog;
use App\Services\DemoSeeder;

/**
 * Outil de démonstration TEMPORAIRE (staff) : crée 1–2 boutiques d'exemple pour
 * illustrer une fonctionnalité, puis les retire. Données clairement « démo »
 * (@afriklink.demo) → purge propre. À supprimer une fois la démo terminée.
 */
final class AdminDemoController
{
    public function index(Request $request): void
    {
        view('admin/demo', [
            'page_title' => 'Démo',
            'count'      => DemoSeeder::count(),
            'showcase'   => DemoSeeder::showcaseUrl(),
        ]);
    }

    public function seed(Request $request): void
    {
        if (!is_admin()) {
            abort(404);
        }
        $res = DemoSeeder::seedSmall(2);
        AuditLog::record((int) current_user_id(), 'demo.seed_small', 'demo', null, $res, $request->ipBinary());
        flash('success', sprintf('%d boutique(s) et %d produit(s) de démo créés.', $res['boutiques'], $res['products']));
        redirect('/admin/demo');
    }

    public function purge(Request $request): void
    {
        // Outil DESTRUCTEUR (DELETE) → admin STRICT, jamais un simple modérateur.
        if (!is_admin()) {
            abort(404);
        }
        $n = DemoSeeder::purge();
        AuditLog::record((int) current_user_id(), 'demo.purge', 'demo', null, ['boutiques' => $n], $request->ipBinary());
        flash('success', sprintf('Démo retirée : %d boutique(s) supprimée(s).', $n));
        redirect('/admin/demo');
    }
}
