<?php
declare(strict_types=1);

/**
 * Vercel serverless entrypoint.
 *
 * Vercel's PHP runtime invokes a function file (here, api/index.php) instead of an
 * Apache-served public/index.php. This thin entrypoint reuses the exact same
 * bootstrap + router as the rest of the app — no logic is duplicated. Routing that
 * the .htaccess handled on Apache is configured in vercel.json.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Router();
$router->load(require CONFIG_PATH . '/routes.php');
$router->dispatch(App\Request::capture());
