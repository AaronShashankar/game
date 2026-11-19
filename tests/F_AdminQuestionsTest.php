<?php
// Admin question bank: list, edit, archive, CSV import.

return function (TestKit $t): void {
    $t->test('question endpoints are admin-only', function () use ($t) {
        $t->fails('UNAUTHENTICATED', $t->player()->request('GET', '/api/admin/questions'), 401);
    });

    $t->test('list and filter questions with answer stats', function () use ($t) {
        $admin = $t->admin();
        $all = $admin->ok('GET', '/api/admin/questions');
        $t->ok(count($all['questions']) >= 140, 'seed bank is loaded');
        $t->ok(in_array('Science', $all['subjects'], true));
        $hard = $admin->ok('GET', '/api/admin/questions', ['subject' => 'Science', 'difficulty' => 'Hard'])['questions'];
        $t->ok(count($hard) > 0);
        foreach ($hard as $q) $t->eq(['Science', 'Hard'], [$q['subject'], $q['difficulty']]);
        $found = $admin->ok('GET', '/api/admin/questions', ['q' => 'Mona Lisa'])['questions'];
        $t->eq(1, count($found));
    });

    $t->test('create, validate, edit and archive a question', function () use ($t) {
        $admin = $t->admin();
        $bad = ['subject' => 'Geography', 'difficulty' => 'Easy', 'prompt' => 'Capital of Peru?', 'options' => ['Lima', 'Lima'], 'answer_idx' => 0];
        $t->fails('INVALID_QUESTION', $admin->request('POST', '/api/admin/questions', $bad));
        $t->fails('INVALID_QUESTION', $admin->request('POST', '/api/admin/questions', ['difficulty' => 'Extreme'] + $bad));

        // A blank option in the middle: the correct answer is resolved before blanks are dropped.
        $q = $admin->ok('POST', '/api/admin/questions', [
            'subject' => 'Geography', 'difficulty' => 'Easy', 'prompt' => 'What is the capital of Peru?',
            'options' => ['Quito', 'Bogotá', '', 'Lima'], 'answer_idx' => 3, 'explanation' => 'Lima is on the Pacific coast.',
        ])['question'];
        $t->eq(['Quito', 'Bogotá', 'Lima'], $q['options']);
        $t->eq(2, $q['answer_idx']);

        $edited = $admin->ok('POST', '/api/admin/questions', ['id' => $q['id'], 'subject' => 'Geography', 'difficulty' => 'Medium',
            'prompt' => 'What is the capital city of Peru?', 'options' => $q['options'], 'answer_idx' => 2])['question'];
        $t->eq('Medium', $edited['difficulty']);
        $t->ok($edited['updated_at'] !== null, 'edited questions are marked so seed refreshes skip them');

        // Archived questions disappear from play (and new subjects appear once active).
        $admin->ok('POST', '/api/admin/questions/status', ['id' => $q['id'], 'active' => false]);
        $p = $t->player();
        $t->fails('INVALID_SUBJECT', $p->request('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Geography']));
        $admin->ok('POST', '/api/admin/questions/status', ['id' => $q['id'], 'active' => true]);
        $run = $p->ok('POST', '/api/runs/start', ['difficulty' => 'Easy', 'subject' => 'Geography'])['run'];
        $served = $p->ok('POST', '/api/runs/question', ['run_id' => $run['run_id'], 'gate_no' => 1]);
        $t->eq($q['id'], $served['question_id']);
    });

    $t->test('CSV import is all-or-nothing and skips duplicates', function () use ($t) {
        $admin = $t->admin();
        $header = 'subject,difficulty,prompt,option1,option2,option3,option4,answer,explanation';
        $good = 'Geography,easy,"Which river flows through Cairo?",Nile,Amazon,Danube,,1,"The Nile flows north through Cairo."';
        $bad = 'Geography,Easy,Which is a continent?,Asia,Asia,,,1,';
        $before = (int) $t->scalar('SELECT COUNT(*) FROM questions');

        $err = $t->fails('CSV_INVALID', $admin->request('POST', '/api/admin/questions/import', ['csv' => "$header\n$good\n$bad\n"]));
        $t->ok(str_contains($err['message'], 'Row 3'), 'names the bad row: ' . $err['message']);
        $t->eq($before, (int) $t->scalar('SELECT COUNT(*) FROM questions'), 'nothing imported');

        $t->fails('CSV_HEADER', $admin->request('POST', '/api/admin/questions/import', ['csv' => "subject,prompt\nA,B\n"]));

        $r = $admin->ok('POST', '/api/admin/questions/import', ['csv' => "\u{FEFF}$header\r\n$good\r\n"]);
        $t->eq([1, 0], [$r['added'], $r['skipped_duplicates']], 'added, skipped');
        $row = $t->db->query("SELECT difficulty, options, answer_idx FROM questions WHERE prompt = 'Which river flows through Cairo?'")->fetch();
        $t->eq(['Easy', ['Nile', 'Amazon', 'Danube'], 0], [$row['difficulty'], json_decode($row['options'], true), (int) $row['answer_idx']]);

        $r = $admin->ok('POST', '/api/admin/questions/import', ['csv' => "$header\n$good\n"]);
        $t->eq(1, $r['skipped_duplicates']);
    });
};
