<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Services\RunService;

final class RunController
{
    public function __construct(private RunService $runs = new RunService()) {}

    public function start(Request $r): array
    {
        $replay = $r->get('replay_of');
        $state = $this->runs->start(
            Auth::playerId(), $r->required('difficulty'), $r->required('subject'),
            $replay === null || $replay === '' ? null : $r->int('replay_of'),
        );
        return ['run' => $state];
    }

    public function question(Request $r): array
    {
        return $this->runs->question(Auth::playerId(), $r->int('run_id'), $r->int('gate_no'));
    }

    public function answer(Request $r): array
    {
        return $this->runs->answer(Auth::playerId(), $r->int('run_id'), $r->int('gate_no'), $r->int('attempt_no'), $r->int('option'));
    }

    public function powerup(Request $r): array
    {
        $gate = $r->get('gate_no');
        return $this->runs->usePowerup(Auth::playerId(), $r->int('run_id'), $r->required('type'),
            $gate === null || $gate === '' ? null : $r->int('gate_no'));
    }

    public function complete(Request $r): array
    {
        return $this->runs->complete(Auth::playerId(), $r->int('run_id'));
    }

    public function timeout(Request $r): array
    {
        return $this->runs->timeout(Auth::playerId(), $r->int('run_id'));
    }
}
