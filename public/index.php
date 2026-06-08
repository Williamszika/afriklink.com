<?php
declare(strict_types=1);

/**
 * Front controller — the ONLY PHP file exposed to the web.
 * Everything else lives above the webroot. The document root must point here
 * (Hostinger hPanel). See references/architecture.md §3.
 */

// Dev convenience: under `php -S ... public/index.php`, serve real files directly
// and route everything else through the front controller. Production uses .htaccess.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Router();
$router->load(require CONFIG_PATH . '/routes.php');
$router->dispatch(App\Request::capture());
