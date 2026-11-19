<?php
// api.php

// Define API routes
$apiRoutes = [
    '/api/user_login.php' => 'api/user_login.php',
    '/api/admin_login.php' => 'api/admin_login.php',
    '/api/check_in.php' => 'api/check_in.php',
    '/api/game_session_api.php' => 'api/game_session_api.php',
    '/api/leaderboard.php' => 'api/leaderboard.php',
    '/api/logout.php' => 'api/logout.php',
    '/api/register.php' => 'api/register.php',
    '/api/sync_profile.php' => 'api/sync_profile.php',
    '/api/user_profile.php' => 'api/user_profile.php',
    '/api/admin_api.php' => 'api/admin_api.php',
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
