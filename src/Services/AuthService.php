<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Auth;
use App\Repositories\AdminRepository;
use App\Repositories\SecurityLogRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;
use App\Support\Database;

final class AuthService
{
    private const MAX_FAILURES = 10;       // per IP...
    private const FAILURE_WINDOW_MIN = 15; // ...per 15 minutes

    public function __construct(
        private UserRepository $users = new UserRepository(),
        private AdminRepository $admins = new AdminRepository(),
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
        private SecurityLogRepository $log = new SecurityLogRepository(),
    ) {}

    public function register(string $username, string $email, string $password, string $confirm): void
    {
        self::validateUsername($username);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw ApiError::badRequest('INVALID_EMAIL', 'Invalid email format');
        if (strlen($password) < 6) throw ApiError::badRequest('WEAK_PASSWORD', 'Password must be at least 6 characters');
        if ($password !== $confirm) throw ApiError::badRequest('PASSWORD_MISMATCH', 'Passwords do not match');
        if ($this->users->usernameOrEmailTaken($username, $email)) {
            throw ApiError::conflict('TAKEN', 'Username or email already exists');
        }

        Database::transaction(function () use ($username, $email, $password) {
            $id = $this->users->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
            $this->progression->credit($id, GameRules::STARTER_COINS, 0, 'signup', 'starter');
            foreach (GameRules::POWERUPS as $code) $this->shop->grant($id, $code, GameRules::STARTER_POWERUPS);
        });
    }

    public function loginPlayer(string $email, string $password, string $ip): array
    {
        $this->throttle($ip);
        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $this->log->log('login_failed', $email, $ip);
            throw ApiError::unauthorized('Invalid email or password');
        }
        if (!(int) $user['is_active']) {
            $this->log->log('login_failed:deactivated', $email, $ip);
            throw ApiError::forbidden('This account is deactivated');
        }
        Auth::loginPlayer($user);
        $this->users->recordLogin((int) $user['id']);
        $this->log->log('login_success', $user['username'], $ip);
        return $user;
    }

    public function loginAdmin(string $email, string $password, string $ip): array
    {
        $this->throttle($ip);
        $admin = $this->admins->findByEmail($email);
        if (!$admin || !password_verify($password, $admin['password'])) {
            $this->log->log('login_failed:admin', $email, $ip);
            throw ApiError::unauthorized('Invalid email or password');
        }
        Auth::loginAdmin($admin);
        $this->log->log('login_success:admin', $admin['username'], $ip);
        return $admin;
    }

    public function changePlayerPassword(int $userId, string $current, string $new): void
    {
        $user = $this->users->find($userId);
        if (!password_verify($current, $user['password'])) throw ApiError::badRequest('WRONG_PASSWORD', 'Current password is incorrect');
        if (strlen($new) < 6) throw ApiError::badRequest('WEAK_PASSWORD', 'Password must be at least 6 characters');
        // Signs out the player's other devices; this one stays signed in.
        Auth::setVersion($this->users->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT)));
    }

    // Previously this looked admins up in the users table, so it never worked.
    public function changeAdminPassword(int $adminId, string $current, string $new): void
    {
        $admin = $this->admins->find($adminId);
        if (!$admin || !password_verify($current, $admin['password'])) {
            throw ApiError::badRequest('WRONG_PASSWORD', 'Current password is incorrect');
        }
        if (strlen($new) < 8) throw ApiError::badRequest('WEAK_PASSWORD', 'Password must be at least 8 characters');
        Auth::setVersion($this->admins->updatePassword($adminId, password_hash($new, PASSWORD_DEFAULT)));
    }

    public static function validateUsername(string $username): void
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
            throw ApiError::badRequest('INVALID_USERNAME', 'Username must be 3–30 letters, numbers, dots, dashes or underscores');
        }
    }

    private function throttle(string $ip): void
    {
        if ($this->log->recentFailures($ip, self::FAILURE_WINDOW_MIN) >= self::MAX_FAILURES) {
            throw ApiError::tooMany('Too many failed logins. Try again in a few minutes.');
        }
    }
}
