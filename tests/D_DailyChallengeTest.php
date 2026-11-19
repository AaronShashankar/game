<?php
// The daily maze and "challenge a friend".

use App\Services\GameRules;

return function (TestKit $t): void {
    $t->test('daily maze: same seed and question order for everyone, resumable, one attempt', function () use ($t) {
        $a = $t->player('day');
        $b = $t->player('day');
        $ra = $a->ok('POST', '/api/runs/start', ['mode' => 'daily']);
        $rb = $b->ok('POST', '/api/runs/start', ['mode' => 'daily']);
        $t->eq('daily', $ra['run']['mode']);
        $t->eq(GameRules::DAILY_DIFFICULTY, $ra['run']['difficulty']);
        $t->eq($ra['run']['seed'], $rb['run']['seed'], 'shared seed');
        $t->eq(false, $ra['resumed']);

        $qa = $a->ok('POST', '/api/runs/question', ['run_id' => $ra['run']['run_id'], 'gate_no' => 1]);
        $qb = $b->ok('POST', '/api/runs/question', ['run_id' => $rb['run']['run_id'], 'gate_no' => 1]);
        $t->eq($qa['question_id'], $qb['question_id'], 'same first question');

        // Starting a normal run doesn't abandon the daily; starting the daily again resumes it.
        $a->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Science']);
        $again = $a->ok('POST', '/api/runs/start', ['mode' => 'daily']);
        $t->eq(true, $again['resumed']);
        $t->eq($ra['run']['run_id'], $again['run']['run_id']);

        $done = $t->finishRun($a, $again['run'], ageSeconds: 200);
        $t->eq(['coins' => GameRules::DAILY_BONUS_COINS, 'xp' => GameRules::DAILY_BONUS_XP], $done['daily_bonus']);
        $t->eq(1, $done['daily']['rank']);
        $t->eq(null, $done['share_code'], 'daily runs are not shareable');
        $t->ok(in_array('daily_first', array_column($done['new_achievements'], 'code'), true));

        $t->fails('DAILY_DONE', $a->request('POST', '/api/runs/start', ['mode' => 'daily']), 409);
        $t->fails('DAILY_DONE', $a->request('POST', '/api/runs/start', ['difficulty' => 'Medium', 'subject' => 'Mixed Mode', 'replay_of' => $ra['run']['run_id']]));

        // A faster finisher ranks above.
        $doneB = $t->finishRun($b, $rb['run'], ageSeconds: 100);
        $t->eq(1, $doneB['daily']['rank']);
        $board = $b->ok('GET', '/api/leaderboard', ['type' => 'daily'])['leaderboard'];
        $t->eq([$b->username, $a->username], array_column($board, 'username'));
        $status = $a->ok('GET', '/api/daily')['daily'];
        $t->eq('completed', $status['you']['status']);
        $t->eq(2, $status['you']['rank']);
        $t->assertLedgerBalanced($a->id);
    });

    $t->test('challenge: same maze and same questions per gate, times compared', function () use ($t) {
        $host = $t->player('host');
        $run = $host->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Mathematics'])['run'];
        $asked = [];
        [$q1] = $t->answerGate($host, $run['run_id'], 1, correct: false);   // gate 1, attempt 1 (wrong)
        foreach ($run['gates'] as $g) {
            [$q] = $t->answerGate($host, $run['run_id'], $g['gate_no']);
            $asked[$g['gate_no']] = $q['question_id'];
        }
        $t->ageRun($run['run_id'], 90);
        $code = $host->ok('POST', '/api/runs/complete', ['run_id' => $run['run_id']])['share_code'];

        $t->fails('OWN_CHALLENGE', $host->request('POST', '/api/runs/start', ['mode' => 'challenge', 'code' => $code]));
        $friend = $t->player('friend');
        $t->fails('NOT_FOUND', $friend->request('POST', '/api/runs/start', ['mode' => 'challenge', 'code' => 'ZZZZZZZZ']), 404);

        $c = $friend->ok('POST', '/api/runs/start', ['mode' => 'challenge', 'code' => strtolower($code)])['run'];
        $t->eq('challenge', $c['mode']);
        $t->eq($run['seed'], $c['seed']);
        $t->eq($host->username, $c['opponent']['username']);

        // Gate 1: attempt 1 gets the host's first (missed) question; attempt 2 the one they then got right.
        [$f1] = $t->answerGate($friend, $c['run_id'], 1, correct: false);
        $t->eq($q1['question_id'], $f1['question_id']);
        [$f1b] = $t->answerGate($friend, $c['run_id'], 1);
        $t->eq($asked[1], $f1b['question_id']);
        [$f2] = $t->answerGate($friend, $c['run_id'], 2);
        $t->eq($asked[2], $f2['question_id']);
        [$f3] = $t->answerGate($friend, $c['run_id'], 3);
        $t->eq($asked[3], $f3['question_id']);

        $t->ageRun($c['run_id'], 30);
        $done = $friend->ok('POST', '/api/runs/complete', ['run_id' => $c['run_id']]);
        $t->eq($host->username, $done['challenge']['opponent']);
        $t->eq(true, $done['challenge']['won']);
        $t->ok(in_array('challenge_win', array_column($done['new_achievements'], 'code'), true));

        $page = (new Client($t->base))->request('GET', '/challenge?code=' . $code);
        $t->eq(200, $page['status']);
        $t->ok(str_contains($page['body'], htmlspecialchars($host->username)), 'challenge page names the host');
    });
};
