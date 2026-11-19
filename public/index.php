<?php
// Front controller: every request that isn't a static file ends up here.

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Http\Auth;
use App\Http\Request;
use App\Http\Router;

Auth::start();

// Path relative to the app, e.g. "/game/dashboard/" -> "/dashboard".
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');

$router = new Router();
(require APP_ROOT . '/src/routes.php')($router);
$router->dispatch(new Request($_SERVER['REQUEST_METHOD'] ?? 'GET', $path));
