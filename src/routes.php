<?php
// Every URL the app answers. Guards: 'player' or 'admin' (null = public).

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RunController;
use App\Http\Router;
use App\Repositories\QuestionRepository;
use App\Services\GameRules;

return function (Router $r): void {
    $page = fn (string $view, array $data = []) => fn () => view($view, $data);
    // Pages that show the game rules or subject list get them from the server.
    $withRules = fn (string $view) => fn () => view($view, [
        'difficulties' => GameRules::DIFFICULTIES,
        'subjects'     => (new QuestionRepository())->subjects(),
    ]);

    // ---- Pages ---------------------------------------------------------------
    $r->get('/',               $withRules('landing'));
    $r->get('/user',           $page('user/login'));
    $r->get('/register',       $page('user/register'));
    $r->get('/howtoplay',      $withRules('user/howtoplay'));
    $r->get('/leaderboard',    $page('user/leaderboard'));
    $r->get('/admin',          $page('admin/login'));
    $r->get('/dashboard',      $withRules('user/dashboard'), 'player');
    $r->get('/profile',        $page('user/profile'), 'player');
    $r->get('/shop',           $page('user/shop'), 'player');
    $r->get('/choose-subject', $withRules('user/choose-subject'), 'player');
    $r->get('/game',           $withRules('user/game'), 'player');
    $r->get('/admin-panel',    $page('admin/panel'), 'admin');

    // ---- Auth ----------------------------------------------------------------
    $auth = new AuthController();
    $r->post('/api/auth/register',    [$auth, 'register']);
    $r->post('/api/auth/login',       [$auth, 'login']);
    $r->post('/api/auth/admin-login', [$auth, 'adminLogin']);
    $r->post('/api/auth/logout',      [$auth, 'logout']);
    $r->post('/api/auth/password',    [$auth, 'changePassword'], 'player');

    // ---- Player --------------------------------------------------------------
    $player = new PlayerController();
    $r->get('/api/leaderboard', [$player, 'leaderboard']);
    $r->get('/api/me',          [$player, 'me'], 'player');
    $r->post('/api/me',         [$player, 'updateMe'], 'player');
    $r->post('/api/checkin',    [$player, 'checkin'], 'player');
    $r->post('/api/spin',       [$player, 'spin'], 'player');
    $r->get('/api/shop',        [$player, 'shop'], 'player');
    $r->post('/api/shop/buy',   [$player, 'buy'], 'player');

    // ---- Game runs -----------------------------------------------------------
    $run = new RunController();
    $r->post('/api/runs/start',    [$run, 'start'], 'player');
    $r->post('/api/runs/question', [$run, 'question'], 'player');
    $r->post('/api/runs/answer',   [$run, 'answer'], 'player');
    $r->post('/api/runs/powerup',  [$run, 'powerup'], 'player');
    $r->post('/api/runs/complete', [$run, 'complete'], 'player');
    $r->post('/api/runs/timeout',  [$run, 'timeout'], 'player');

    // ---- Admin ---------------------------------------------------------------
    $admin = new AdminController();
    $r->get('/api/admin/stats',          [$admin, 'stats'], 'admin');
    $r->get('/api/admin/users',          [$admin, 'users'], 'admin');
    $r->post('/api/admin/users',         [$admin, 'addUser'], 'admin');
    $r->post('/api/admin/users/delete',  [$admin, 'deleteUser'], 'admin');
    $r->get('/api/admin/activity',       [$admin, 'activity'], 'admin');
    $r->get('/api/admin/security-logs',  [$admin, 'securityLogs'], 'admin');
    $r->get('/api/admin/analytics',      [$admin, 'analytics'], 'admin');
    $r->get('/api/admin/export',         [$admin, 'export'], 'admin');
    $r->post('/api/admin/password',      [$admin, 'changePassword'], 'admin');
};
