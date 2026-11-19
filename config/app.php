<?php
// Application configuration. Values come from .env (see .env.example);
// the defaults below match a stock XAMPP install.

return [
    'db' => [
        'host'    => env('DB_HOST', '127.0.0.1'),
        'port'    => (int) env('DB_PORT', '3306'),
        'name'    => env('DB_NAME', 'mazequest'),
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
    ],

    // URL prefix the app is served under, e.g. "/game" for http://localhost/game/.
    // Leave empty to auto-detect (works for both htdocs/game and a public/ DocumentRoot).
    'base_path' => env('BASE_PATH', null),

    // Show exception details in API responses. Never enable in production.
    'debug' => env('APP_DEBUG', 'false') === 'true',
];
