<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private AuthService $auth = new AuthService()) {}

    public function register(Request $r): array
    {
        $this->auth->register($r->required('username'), $r->required('email'), $r->required('password'), $r->required('confirm'));
        return ['message' => 'Registration successful. Please log in.'];
    }

    public function login(Request $r): array
    {
        $user = $this->auth->loginPlayer($r->required('email'), $r->required('password'), $r->ip());
        return ['username' => $user['username']];
    }

    public function adminLogin(Request $r): array
    {
        $admin = $this->auth->loginAdmin($r->required('email'), $r->required('password'), $r->ip());
        return ['username' => $admin['username']];
    }

    public function logout(Request $r): array
    {
        Auth::logout();
        return [];
    }

    public function changePassword(Request $r): array
    {
        $this->auth->changePlayerPassword(Auth::playerId(), $r->required('current_password'), $r->required('new_password'));
        return ['message' => 'Password updated'];
    }
}
