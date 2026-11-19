<?php
// Global helper functions
function redirect($path) {
    header("Location: $path");
    exit;
}

function asset($path) {
    // Assuming root path based on application configuration or simple relative logic
    return "/game/assets/" . ltrim($path, '/');
}

function url($path) {
    return "/game/" . ltrim($path, '/');
}

// ---- Session / auth guards ----
// Note: admin sessions store admin.admin_id in $_SESSION['user_id'], which is a
// different ID space from users.id. Player-facing code must check the role, not
// just "logged in", or an admin session would read/write an unrelated player row.

function startSessionOnce() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isPlayer() {
    return !empty($_SESSION['loggedin']) && ($_SESSION['role'] ?? '') === 'user' && isset($_SESSION['user_id']);
}

function isAdmin() {
    return !empty($_SESSION['loggedin']) && ($_SESSION['role'] ?? '') === 'admin';
}

// Page guards: redirect to the matching login page.
function requirePlayerPage() {
    if (!isPlayer()) redirect(url('user'));
}

function requireAdminPage() {
    if (!isAdmin()) redirect(url('admin'));
}

// API guard: JSON 401 instead of a redirect.
function requirePlayerApi() {
    if (!isPlayer()) {
        jsonResponse(false, [], 'Not authenticated', 401);
    }
    return (int)$_SESSION['user_id'];
}

function jsonResponse($success, $data = [], $message = '', $status = 200) {
    http_response_code($status);
    $response = ['success' => $success];
    if ($message) {
        $response['message'] = $message;
    }
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit;
}
