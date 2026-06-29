<?php
// Front Controller
session_start();

require_once __DIR__ . '/Config/database.php';
require_once __DIR__ . '/Utils/helpers.php';
// We'll require AuthService if it exists

// Simple Router
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Base path is /game/
$basePath = '/game/';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Ensure the URI has a leading slash
if (empty($requestUri) || $requestUri[0] !== '/') {
    $requestUri = '/' . $requestUri;
}

// Remove trailing slash for matching (unless it's just '/')
if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
    $requestUri = rtrim($requestUri, '/');
}

// Check if this is an API route
if (strpos($requestUri, '/api/') === 0) {
    require_once __DIR__ . '/Routes/api.php';
} else {
    require_once __DIR__ . '/Routes/web.php';
}
