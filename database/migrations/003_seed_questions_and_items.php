<?php
// Seeds the question bank (previously hard-coded in the game's JavaScript,
// answers included) and the shop catalogue (previously prices in HTML).

return function (PDO $db, Migrator $m): void {
    if ((int) $db->query('SELECT COUNT(*) FROM questions')->fetchColumn() === 0) {
        $questions = json_decode(file_get_contents(__DIR__ . '/../seeds/questions.json'), true, flags: JSON_THROW_ON_ERROR);
        $ins = $db->prepare('INSERT INTO questions (subject, prompt, options, answer_idx) VALUES (?, ?, ?, ?)');
        foreach ($questions as $q) {
            $ins->execute([$q['subject'], $q['prompt'], json_encode($q['options'], JSON_UNESCAPED_UNICODE), $q['answer_idx']]);
        }
        $m->say('seeded ' . count($questions) . ' questions');
    }

    // code, name, description, icon, kind, price, grants, active, sort
    $items = [
        ['timeFreeze',   'Time Freeze',    'Freeze the timer for 10 seconds',  '⏱️', 'powerup', 25,  ['timeFreeze' => 1], 1, 10],
        ['hint',         'Hint Key',       'Highlight the nearest gate',       '🔍', 'powerup', 20,  ['hint' => 1], 1, 20],
        ['skip',         'Skip Gate',      'Open the nearest gate',            '⏩', 'powerup', 30,  ['skip' => 1], 1, 30],
        ['scoreDoubler', 'Score Doubler',  'Double your next gate reward',     '💰', 'powerup', 40,  ['scoreDoubler' => 1], 1, 40],
        ['pack',         'Power-Up Pack',  '1 of each power-up',               '🎒', 'pack',    100, ['timeFreeze' => 1, 'hint' => 1, 'skip' => 1, 'scoreDoubler' => 1], 1, 50],
        ['megaPack',     'Mega Pack',      '3 of each power-up',               '💎', 'pack',    250, ['timeFreeze' => 3, 'hint' => 3, 'skip' => 3, 'scoreDoubler' => 3], 1, 60],
        ['skin2',        'Blue Skin',      'Play as a cyan explorer',          '🔵', 'skin',    200, ['skin2' => 1], 1, 70],
        ['skin3',        'Red Hero',       'Play as a red hero',               '🔴', 'skin',    350, ['skin3' => 1], 1, 80],
        // Listed in the old shop page but never implemented in the game.
        // Kept inactive so they aren't sold until they do something.
        ['forest',       'Forest Theme',   'Coming soon',                      '🌲', 'theme',   300, ['forest' => 1], 0, 90],
        ['space',        'Space Theme',    'Coming soon',                      '🌌', 'theme',   450, ['space' => 1], 0, 100],
        ['speedBoost',   'Speed Boost',    'Coming soon',                      '⚡', 'powerup', 150, ['speedBoost' => 1], 0, 110],
        ['shield',       'Shield',         'Coming soon',                      '🛡️', 'powerup', 250, ['shield' => 1], 0, 120],
    ];
    $ins = $db->prepare('INSERT INTO items (code, name, description, icon, kind, price, grants, active, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), icon = VALUES(icon),
                           kind = VALUES(kind), price = VALUES(price), grants = VALUES(grants), active = VALUES(active),
                           sort_order = VALUES(sort_order)');
    foreach ($items as $i) {
        $i[6] = json_encode($i[6]);
        $ins->execute($i);
    }
    $m->say('seeded ' . count($items) . ' shop items');
};
