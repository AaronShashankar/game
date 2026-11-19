<?php

declare(strict_types=1);

namespace App\Http;

// Session state. Players and admins use separate keys (player_id / admin_id)
// because they come from different tables with overlapping IDs.
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_set_cookie_params([
            'path'     => base_path() === '' ? '/' : base_path() . '/',
            'httponly' => true,
            'samesite' => 'Lax',   // cross-site POSTs don't carry the cookie (basic CSRF protection)
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    public static function loginPlayer(array $user): void
    {
        self::fresh();
        $_SESSION['player_id'] = (int) $user['id'];
        $_SESSION['username']  = $user['username'];
    }

    public static function loginAdmin(array $admin): void
    {
        self::fresh();
        $_SESSION['admin_id'] = (int) $admin['admin_id'];
        $_SESSION['username'] = $admin['username'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function playerId(): ?int { return isset($_SESSION['player_id']) ? (int) $_SESSION['player_id'] : null; }
    public static function adminId(): ?int  { return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null; }

    // New session ID on every login (prevents session fixation) with no
    // leftover keys from a previous login.
    private static function fresh(): void
    {
        session_regenerate_id(true);
        $_SESSION = [];
    }
}
