<?php
declare(strict_types=1);

/**
 * Jeu de DÉMONSTRATION (CLI) : 50 boutiques publiées (Europe + Côte d'Ivoire) +
 * produits, multi-boutique. Logique partagée dans App\Services\DemoSeeder (aussi
 * utilisée par la route d'admin protégée /admin/demo).
 *
 *   php database/seed_demo.php --force            # local/staging
 *   php database/seed_demo.php --force --prod     # PRODUCTION (confirmation explicite)
 *   php database/seed_demo.php --purge            # retire TOUTE la démo (réversible)
 *   php database/seed_demo.php --purge --prod     # retire la démo en PRODUCTION
 *
 * En production, la démo est VISIBLE PUBLIQUEMENT : à retirer (--purge) avant un
 * vrai lancement. Données identifiables (@afriklink.demo) pour un retrait propre.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\DemoSeeder;

$purge     = in_array('--purge', $argv, true);
$allowProd = in_array('--prod', $argv, true);
$isProd    = ($_ENV['APP_ENV'] ?? 'production') === 'production';

if (!$purge && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Refus : ajoutez --force (semer) ou --purge (retirer la démo).\n");
    exit(1);
}
if ($isProd && !$allowProd) {
    fwrite(STDERR, "Refus : APP_ENV=production. Ajoutez --prod pour confirmer EXPLICITEMENT (la démo sera visible par vos visiteurs ; retirable avec --purge).\n");
    exit(1);
}

if ($purge) {
    $n = DemoSeeder::purge();
    fwrite(STDOUT, "✅ Démo retirée : {$n} boutique(s) de démo (+ produits + comptes @afriklink.demo) supprimées.\n");
    exit(0);
}

fwrite(STDOUT, "→ (Re)peuplement de la démo…\n");
$r = DemoSeeder::seed();
fwrite(STDOUT, "✅ Terminé : {$r['boutiques']} boutiques publiées, {$r['products']} produits, {$r['sellers']} vendeurs.\n");
fwrite(STDOUT, "Connexion vendeur de démo : seed1@afriklink.demo / demo1234\n");
