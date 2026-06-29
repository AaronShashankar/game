<?php
// web.php

// Define the routes mapping to their respective page files
$routes = [
    '/' => 'Pages/index.php',
    '/user' => 'Pages/User/login.php',
    '/admin' => 'Pages/Admin/login.php',
    '/register' => 'Pages/User/register.php',
    '/dashboard' => 'Pages/User/dashboard.php',
    '/game' => 'Pages/User/game.php',
    '/profile' => 'Pages/User/profile.php',
    '/howtoplay' => 'Pages/User/howtoplay.php',
    '/leaderboard' => 'Pages/User/leaderboard.php',
    '/shop' => 'Pages/User/shop.php',
    '/choose-subject' => 'Pages/User/choose-subject.php',
    '/admin-panel' => 'Pages/Admin/admin-panel.php'
];

if (array_key_exists($requestUri, $routes)) {
    $file = __DIR__ . '/../' . $routes[$requestUri];
    if (file_exists($file)) {
        require_once $file;
    } else {
        http_response_code(404);
        echo "404 Not Found";
    }
} else {
    http_response_code(404);
    echo "404 Not Found";
}
