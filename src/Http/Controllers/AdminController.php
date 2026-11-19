<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Services\AdminService;
use App\Services\AuthService;

final class AdminController
{
    public function __construct(
        private AdminService $admin = new AdminService(),
        private AuthService $auth = new AuthService(),
    ) {}

    public function stats(Request $r): array         { return ['stats' => $this->admin->stats()]; }
    public function users(Request $r): array         { return ['users' => $this->admin->users()]; }
    public function activity(Request $r): array      { return ['activity' => $this->admin->activity()]; }
    public function securityLogs(Request $r): array  { return ['logs' => $this->admin->securityLogs()]; }
    public function analytics(Request $r): array     { return ['analytics' => $this->admin->analytics()]; }
    public function export(Request $r): array        { return ['data' => $this->admin->export()]; }

    public function addUser(Request $r): array
    {
        $this->admin->addUser($r->required('username'), $r->required('email'), $r->required('password'),
                              $r->int('coins', 0), $r->int('level', 1));
        return ['message' => 'User added'];
    }

    public function deleteUser(Request $r): array
    {
        $this->admin->deleteUser($r->int('user_id'));
        return ['message' => 'User deleted'];
    }

    public function changePassword(Request $r): array
    {
        $this->auth->changeAdminPassword(Auth::adminId(), $r->required('current_password'), $r->required('new_password'));
        return ['message' => 'Password updated'];
    }
}
