<?php
// api.php

// Define API routes
$apiRoutes = [
    '/api/user_login.php' => 'Api/user_login.php',
    '/api/admin_login.php' => 'Api/admin_login.php',
    '/api/api.php' => 'Api/api.php',
    '/api/check_in.php' => 'Api/check_in.php',
    '/api/game_session_api.php' => 'Api/game_session_api.php',
    '/api/leaderboard.php' => 'Api/leaderboard.php',
    '/api/logout.php' => 'Api/logout.php',
    '/api/register.php' => 'Api/register.php',
    '/api/sync_profile.php' => 'Api/sync_profile.php',
    '/api/user_profile.php' => 'Api/user_profile.php',
    '/api/admin_api.php' => 'Api/admin_api.php',
];

if (array_key_exists($requestUri, $apiRoutes)) {
    $file = __DIR__ . '/../' . $apiRoutes[$requestUri];
    if (file_exists($file)) {
        require_once $file;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'API route not found.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API route not found.']);
}
