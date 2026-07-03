<?php
declare(strict_types=1);

/**
 * Front controller — the ONLY PHP file exposed to the web.
 * Everything else lives above the webroot. The document root must point here
 * (Hostinger hPanel). See references/architecture.md §3.
 */

// Dev convenience: under `php -S ... public/index.php`, serve real files directly
// and route everything else through the front controller. Production uses .htaccess.
// La cible est bornée SOUS public/ via realpath + préfixe (anti-traversée « ../ »),
// même si ce bloc ne s'exécute que sous le serveur de dev (cli-server).
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = realpath(__DIR__ . $path);
    // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename -- chemin résolu + confiné à public/
    if ($file !== false && $file !== __DIR__ && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Router();
$router->load(require CONFIG_PATH . '/routes.php');
$router->dispatch(App\Request::capture());
