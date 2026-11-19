<?php
// Normal runs: questions, answer feedback, pickups, the end-of-run summary,
// spaced repetition and difficulty-tagged questions.

return function (TestKit $t): void {
    $t->test('a question never includes its answer; answering reveals it with an explanation', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Mathematics'])['run'];
        $t->eq('normal', $run['mode']);
        $q = $p->ok('POST', '/api/runs/question', ['run_id' => $run['run_id'], 'gate_no' => 1]);
        $t->ok(!array_key_exists('answer_idx', $q) && !array_key_exists('explanation', $q), 'no answer in the question payload');

        $t->fails('TOO_FAST', $p->request('POST', '/api/runs/answer', ['run_id' => $run['run_id'], 'gate_no' => 1, 'attempt_no' => 1, 'option' => 0]));
        $t->ageQuestion($run['run_id'], 1);
        $right = $t->answerIdx($q['question_id']);
        $a = $p->ok('POST', '/api/runs/answer', ['run_id' => $run['run_id'], 'gate_no' => 1, 'attempt_no' => 1, 'option' => ($right + 1) % count($q['options'])]);
        $t->eq(false, $a['correct']);
        $t->eq($right, $a['correct_idx']);
        $t->ok(is_string($a['explanation']) && $a['explanation'] !== '', 'explanation returned');

        // Retrying the same attempt returns the stored outcome, including the reveal.
        $again = $p->ok('POST', '/api/runs/answer', ['run_id' => $run['run_id'], 'gate_no' => 1, 'attempt_no' => 1, 'option' => $right]);
        $t->eq(true, $again['replayed']);
        $t->eq(false, $again['correct']);

        // The next attempt is a different question.
        $q2 = $p->ok('POST', '/api/runs/question', ['run_id' => $run['run_id'], 'gate_no' => 1]);
        $t->eq(2, $q2['attempt_no']);
        $t->ok($q2['question_id'] !== $q['question_id'], 'new question after a wrong answer');
    });

    $t->test('pickups: valid numbers only, idempotent, paid on finish', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Medium', 'subject' => 'Science'])['run'];
        $t->eq(4, $run['pickup_count']);
        $t->fails('INVALID_PICKUP', $p->request('POST', '/api/runs/pickup', ['run_id' => $run['run_id'], 'pickup_no' => 5]));
        $t->fails('INVALID_PICKUP', $p->request('POST', '/api/runs/pickup', ['run_id' => $run['run_id'], 'pickup_no' => 0]));

        $coinsBefore = $p->ok('GET', '/api/me')['wallet']['coins'];
        $first = $p->ok('POST', '/api/runs/pickup', ['run_id' => $run['run_id'], 'pickup_no' => 2]);
        $t->eq(true, $first['collected']);
        $t->eq(false, $p->ok('POST', '/api/runs/pickup', ['run_id' => $run['run_id'], 'pickup_no' => 2])['collected']);
        $p->ok('POST', '/api/runs/pickup', ['run_id' => $run['run_id'], 'pickup_no' => 4]);
        $t->eq([2, 4], $p->ok('POST', '/api/runs/pickup', ['run_id' => $run['run_id'], 'pickup_no' => 4])['run']['pickups']);
        $t->eq($coinsBefore, $p->ok('GET', '/api/me')['wallet']['coins'], 'pickups pay nothing until the maze is finished');

        $done = $t->finishRun($p, $run);
        $t->eq(10, $done['pickup_coins']);
        $t->eq(2, $done['summary']['pickups']);
        $t->assertLedgerBalanced($p->id);
    });

    $t->test('abandoned runs never pay their pickups', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'History'])['run'];
        $p->ok('POST', '/api/runs/pickup', ['run_id' => $run['run_id'], 'pickup_no' => 1]);
        $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'History']);   // abandons the first
        $t->eq(0, (int) $t->scalar("SELECT COUNT(*) FROM ledger WHERE user_id = ? AND reason = 'pickups'", [$p->id]));
        $t->fails('RUN_NOT_ACTIVE', $p->request('POST', '/api/runs/complete', ['run_id' => $run['run_id']]));
    });

    $t->test('the end-of-run summary lists missed questions with the right answer, and gives a share code', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'English Vocabulary'])['run'];
        [$missedQ] = $t->answerGate($p, $run['run_id'], 1, correct: false);
        $done = $t->finishRun($p, $run);
        $s = $done['summary'];
        $t->eq(4, $s['answered']);
        $t->eq(3, $s['correct']);
        $t->eq(1, $s['wrong']);
        $t->eq(75, $s['accuracy']);
        $t->eq(1, count($s['missed']));
        $t->eq($missedQ['prompt'], $s['missed'][0]['prompt']);
        $t->eq($missedQ['options'][$t->answerIdx($missedQ['question_id'])], $s['missed'][0]['correct_answer']);
        $t->ok($s['duration_seconds'] >= 120, 'duration recorded');
        $t->ok(is_string($done['share_code']) && strlen($done['share_code']) === 8, 'share code');
        $codes = array_column($done['new_achievements'], 'code');
        $t->ok(in_array('first_clear', $codes, true), 'first_clear unlocked');
        $t->ok(!in_array('perfect_run', $codes, true), 'not perfect with a wrong answer');
    });

    $t->test('a perfect run unlocks Flawless', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Science'])['run'];
        $done = $t->finishRun($p, $run);
        $t->ok(in_array('perfect_run', array_column($done['new_achievements'], 'code'), true));
    });

    $t->test('missed questions come back as reviews in the next maze and move up a box', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'History'])['run'];
        [$missed] = $t->answerGate($p, $run['run_id'], 1, correct: false);
        $t->eq(1, (int) $t->scalar('SELECT box FROM question_reviews WHERE user_id = ? AND question_id = ?', [$p->id, $missed['question_id']]));
        $t->eq(1, $p->ok('GET', '/api/me')['learning']['reviews_due']);

        $next = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'History'])['run'];
        [$q, $a] = $t->answerGate($p, $next['run_id'], 1);
        $t->eq($missed['question_id'], $q['question_id'], 'the missed question is served first');
        $t->eq(true, $q['is_review']);
        $t->eq(true, $a['was_review']);
        $row = $t->db->query("SELECT box, due_at > NOW() AS later FROM question_reviews WHERE user_id = {$p->id}")->fetch();
        $t->eq(2, (int) $row['box']);
        $t->eq(1, (int) $row['later'], 'pushed into the future');
        $t->eq(0, $p->ok('GET', '/api/me')['learning']['reviews_due']);
    });

    $t->test('reviews are capped per maze and only fit the chosen subject', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Hard', 'subject' => 'Mathematics'])['run'];
        for ($g = 1; $g <= 3; $g++) $t->answerGate($p, $run['run_id'], $g, correct: false);
        $t->eq(3, $p->ok('GET', '/api/me')['learning']['reviews_due']);

        $science = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Science'])['run'];
        $q = $p->ok('POST', '/api/runs/question', ['run_id' => $science['run_id'], 'gate_no' => 1]);
        $t->eq(false, $q['is_review'], 'no maths review in a science maze');

        $maths = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Hard', 'subject' => 'Mathematics'])['run'];
        $flags = [];
        for ($g = 1; $g <= 4; $g++) {
            [$q] = $t->answerGate($p, $maths['run_id'], $g);
            $flags[] = $q['is_review'];
        }
        $t->eq([true, true, false, false], $flags, 'at most ' . App\Services\GameRules::MAX_REVIEWS_PER_RUN . ' reviews per maze');
    });

    $t->test('questions prefer the maze difficulty', function () use ($t) {
        $p = $t->player();
        $hardMaths = (int) $t->scalar("SELECT COUNT(*) FROM questions WHERE subject = 'Mathematics' AND difficulty = 'Hard' AND active = 1");
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Hard', 'subject' => 'Mathematics'])['run'];
        $seen = [];
        for ($g = 1; $g <= $hardMaths; $g++) {
            [$q] = $t->answerGate($p, $run['run_id'], $g);
            $seen[] = $q['difficulty'];
        }
        $t->eq(array_fill(0, $hardMaths, 'Hard'), $seen);
    });

    $t->test('subject mastery counts distinct questions answered correctly', function () use ($t) {
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'General Knowledge'])['run'];
        $t->finishRun($p, $run);
        $gk = array_values(array_filter($p->ok('GET', '/api/me')['learning']['mastery'], fn ($m) => $m['subject'] === 'General Knowledge'))[0];
        $t->eq(3, $gk['known']);
        $t->eq(100, $gk['accuracy']);
        $t->eq((int) floor(300 / $gk['total']), $gk['mastery']);
    });
};
