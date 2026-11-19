<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Return success
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
