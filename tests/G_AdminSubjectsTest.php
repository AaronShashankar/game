<?php
// Admin subjects: create, show/hide, rename, delete; questions need a known subject.

use App\Services\GameRules;
use App\Services\QuestionService;

return function (TestKit $t): void {
    $start = fn (Client $p, string $subject) => $p->request('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => $subject]);

    $t->test('subject endpoints are admin-only', function () use ($t) {
        $p = $t->player();
        $t->fails('UNAUTHENTICATED', $p->request('GET', '/api/admin/subjects'), 401);
        $t->fails('UNAUTHENTICATED', $p->request('POST', '/api/admin/subjects', ['name' => 'Sneaky']), 401);
    });

    $t->test('existing subjects are migrated, visible, with their picker icon', function () use ($t) {
        $r = $t->admin()->ok('GET', '/api/admin/subjects');
        $t->eq(GameRules::minSubjectQuestions(), $r['min_questions']);
        $science = array_values(array_filter($r['subjects'], fn ($s) => $s['name'] === 'Science'))[0] ?? null;
        $t->ok($science !== null, 'Science is a subject');
        $t->eq(['flask', true], [$science['icon'], $science['visible']]);
        $t->ok($science['active_count'] >= GameRules::minSubjectQuestions(), 'Science has its seeded questions');
    });

    $t->test('create a subject: validation, duplicates and the reserved name', function () use ($t) {
        $admin = $t->admin();
        $t->fails('INVALID_SUBJECT', $admin->request('POST', '/api/admin/subjects', ['name' => '   ']));
        $t->fails('INVALID_SUBJECT', $admin->request('POST', '/api/admin/subjects', ['name' => 'mixed mode']));
        $t->fails('INVALID_SUBJECT', $admin->request('POST', '/api/admin/subjects', ['name' => 'Art', 'icon' => 'no-such-icon']));
        $t->fails('SUBJECT_EXISTS', $admin->request('POST', '/api/admin/subjects', ['name' => 'science']), 409);

        $s = $admin->ok('POST', '/api/admin/subjects', [
            'name' => '  Computer   Software ', 'description' => 'Apps and operating systems', 'icon' => 'monitor',
        ])['subject'];
        $t->eq(['Computer Software', 'Apps and operating systems', 'monitor', false], [$s['name'], $s['description'], $s['icon'], $s['visible']],
               'name tidied, starts hidden');
    });

    $t->test('a new subject stays out of play until it has enough questions and is shown', function () use ($t, $start) {
        $admin = $t->admin();
        $p = $t->player();
        $s = $admin->ok('POST', '/api/admin/subjects', ['name' => 'Networking', 'icon' => 'code'])['subject'];
        $q = fn (int $i, string $subject = 'networking') => ['subject' => $subject, 'difficulty' => 'Easy',
            'prompt' => "Networking test question $i?", 'options' => ['Right', 'Wrong'], 'answer_idx' => 0];

        // A typo doesn't create a subject.
        $err = $t->fails('INVALID_QUESTION', $admin->request('POST', '/api/admin/questions', $q(0, 'Netwroking')));
        $t->ok(str_contains($err['message'], 'Unknown subject'), $err['message']);
        $first = $admin->ok('POST', '/api/admin/questions', $q(1))['question'];
        $t->eq('Networking', $first['subject'], "stored with the subject's own spelling");

        // Hidden: not on the picker, can't be started, not in mastery, and can't be shown yet.
        $picker = $p->request('GET', '/choose-subject')['body'];
        $t->ok(str_contains($picker, 'data-subject="Science"') && str_contains($picker, '#i-flask'), 'picker lists Science with its icon');
        $t->ok(!str_contains($picker, 'data-subject="Networking"'), 'hidden subject is not on the picker');
        $t->fails('INVALID_SUBJECT', $start($p, 'Networking'));
        $mastery = array_column($p->ok('GET', '/api/me')['learning']['mastery'], 'subject');
        $t->ok(!in_array('Networking', $mastery, true) && in_array('Science', $mastery, true), 'mastery lists visible subjects only');
        $err = $t->fails('NOT_ENOUGH_QUESTIONS', $admin->request('POST', '/api/admin/subjects/visibility', ['id' => $s['id'], 'visible' => true]), 409);
        $t->ok(str_contains($err['message'], '1 active question'), $err['message']);

        for ($i = 2; $i <= GameRules::minSubjectQuestions(); $i++) $admin->ok('POST', '/api/admin/questions', $q($i));
        $t->ok($admin->ok('POST', '/api/admin/subjects/visibility', ['id' => $s['id'], 'visible' => true])['subject']['visible']);
        $picker = $p->request('GET', '/choose-subject')['body'];
        $t->ok(str_contains($picker, 'data-subject="Networking"') && str_contains($picker, '#i-code'), 'shown subject is on the picker with its icon');

        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Networking'])['run'];
        $served = $p->ok('POST', '/api/runs/question', ['run_id' => $run['run_id'], 'gate_no' => 1]);
        $t->eq('Networking', $served['subject']);

        // Hiding takes it out of play again.
        $admin->ok('POST', '/api/admin/subjects/visibility', ['id' => $s['id'], 'visible' => false]);
        $t->fails('INVALID_SUBJECT', $start($p, 'Networking'));
    });

    $t->test('renaming a subject renames its questions and past runs', function () use ($t, $start) {
        $admin = $t->admin();
        $id = $t->subject('Astronomy');
        $qid = $admin->ok('POST', '/api/admin/questions', ['subject' => 'Astronomy', 'difficulty' => 'Easy',
            'prompt' => 'Which planet is the largest in the Solar System?', 'options' => ['Jupiter', 'Mars'], 'answer_idx' => 0])['question']['id'];
        $p = $t->player();
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Astronomy'])['run'];

        $t->fails('SUBJECT_EXISTS', $admin->request('POST', '/api/admin/subjects', ['id' => $id, 'name' => 'History']), 409);
        $renamed = $admin->ok('POST', '/api/admin/subjects', ['id' => $id, 'name' => 'Space Science', 'icon' => 'globe'])['subject'];
        $t->eq(['Space Science', true], [$renamed['name'], $renamed['visible']], 'renamed, still visible');
        $t->eq('Space Science', $t->scalar('SELECT subject FROM questions WHERE id = ?', [$qid]), 'question follows the rename');
        $t->eq('Space Science', $t->scalar('SELECT subject FROM runs WHERE id = ?', [$run['run_id']]), 'past run follows the rename');
        $t->fails('INVALID_SUBJECT', $start($p, 'Astronomy'));
        $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Space Science']);
    });

    $t->test('only a subject with no questions can be deleted', function () use ($t) {
        $admin = $t->admin();
        $typo = $admin->ok('POST', '/api/admin/subjects', ['name' => 'Histroy'])['subject'];
        $admin->ok('POST', '/api/admin/subjects/delete', ['id' => $typo['id']]);
        $t->eq(0, (int) $t->scalar('SELECT COUNT(*) FROM subjects WHERE id = ?', [$typo['id']]));

        $science = (int) $t->scalar("SELECT id FROM subjects WHERE name = 'Science'");
        $t->fails('SUBJECT_IN_USE', $admin->request('POST', '/api/admin/subjects/delete', ['id' => $science]), 409);
        $t->fails('NOT_FOUND', $admin->request('POST', '/api/admin/subjects/delete', ['id' => 999999]), 404);
    });

    $t->test('CSV import needs known subjects and uses their spelling', function () use ($t) {
        $admin = $t->admin();
        $header = implode(',', QuestionService::CSV_COLUMNS);
        $err = $t->fails('CSV_INVALID', $admin->request('POST', '/api/admin/questions/import',
                         ['csv' => "$header\nUnknownland,Easy,Where is Unknownland?,Here,There,,,1,\n"]));
        $t->ok(str_contains($err['message'], 'Row 2: Unknown subject'), $err['message']);

        $prompt = 'Import test: which planet is called the red planet?';
        $r = $admin->ok('POST', '/api/admin/questions/import', ['csv' => "$header\nscience,Easy,$prompt,Mars,Venus,,,1,\n"]);
        $t->eq(1, $r['added']);
        $t->eq('Science', $t->scalar('SELECT subject FROM questions WHERE prompt = ?', [$prompt]));
    });
};
