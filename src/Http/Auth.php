<?php

declare(strict_types=1);

namespace App\Http;

use App\Repositories\AdminRepository;
use App\Repositories\UserRepository;

// Session state. Players and admins use separate keys (player_id / admin_id)
// because they come from different tables with overlapping IDs.
//
// The session also holds the account's auth_version from login time.
// check() compares it with the database on every guarded request, so a
// deleted or deactivated account, or one whose password changed elsewhere,
// is signed out straight away instead of when the session happens to expire.
final class Auth
{
    /** @var array<string, bool> check() results for this request, by guard */
    private static array $checked = [];

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
        $_SESSION['auth_version'] = (int) ($user['auth_version'] ?? 0);
    }

    public static function loginAdmin(array $admin): void
    {
        self::fresh();
        $_SESSION['admin_id'] = (int) $admin['admin_id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['auth_version'] = (int) ($admin['auth_version'] ?? 0);
    }

    // After this session changed the password: keep it signed in at the new version.
    public static function setVersion(int $version): void
    {
        $_SESSION['auth_version'] = $version;
    }

    // Is the session signed in as $guard ('player' or 'admin'), and does that
    // account still exist, stay active, and have the same auth_version?
    // A stale session is destroyed.
    public static function check(string $guard): bool
    {
        if (isset(self::$checked[$guard])) return self::$checked[$guard];

        $id = $guard === 'player' ? self::playerId() : self::adminId();
        if ($id === null) return self::$checked[$guard] = false;   // not signed in as this role: leave the session alone

        $row = $guard === 'player' ? (new UserRepository())->find($id) : (new AdminRepository())->find($id);
        $valid = $row !== null
            && (int) ($row['auth_version'] ?? 0) === (int) ($_SESSION['auth_version'] ?? 0)
            && ($guard !== 'player' || (int) $row['is_active'] === 1);

        if (!$valid) self::logout();   // signed in as this role, but stale
        return self::$checked[$guard] = $valid;
    }

    public static function logout(): void
    {
        self::$checked = [];
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
        self::$checked = [];
        $_SESSION = [];
    }
}
