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
