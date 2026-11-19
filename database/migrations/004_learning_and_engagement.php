<?php
// Learning and engagement features:
//   questions        difficulty, explanation, updated_at (+ the expanded seed bank)
//   question_reviews spaced repetition of questions a player got wrong
//   runs             mode (normal/daily/challenge), share codes, pickups, summary fields
//   daily_mazes      one shared seed per day
//   weekly_*         weekly leaderboard prizes, settled once per week
//   user_achievements replaces user_badges (kept as legacy_user_badges)
//   users            equipped skin / theme
//   items            streak freeze perk; forest and space themes go on sale

return function (PDO $db, Migrator $m): void {
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    // ---- Questions ----------------------------------------------------------
    $m->addColumn('questions', 'difficulty', "ENUM('Easy','Medium','Hard') NOT NULL DEFAULT 'Medium' AFTER subject");
    $m->addColumn('questions', 'explanation', 'VARCHAR(500) NULL AFTER answer_idx');
    // NULL = still exactly as seeded; set when an admin edits or imports a question.
    // Seed refreshes only touch rows where this is NULL, so admin edits are never overwritten.
    $m->addColumn('questions', 'updated_at', 'DATETIME NULL');
    if (!$m->indexExists('questions', 'idx_pick')) {
        $db->exec('ALTER TABLE questions ADD KEY idx_pick (active, subject, difficulty)');
    }

    // Prompts reworded in the new bank: rename in place so answer history keeps its question id.
    $renamed = [
        'How many bones are in the human body?'          => 'How many bones are in the adult human body?',
        'Who discovered America?'                        => 'Whose 1492 voyage for Spain began lasting European contact with the Americas?',
        'Which ancient civilization built the pyramids?' => 'Which ancient civilisation built the pyramids of Giza?',
        'Who was the first man on the moon?'             => 'Who was the first person to walk on the Moon?',
    ];
    $rename = $db->prepare('UPDATE questions SET prompt = ? WHERE prompt = ? AND updated_at IS NULL');
    foreach ($renamed as $old => $new) $rename->execute([$new, $old]);

    // Upsert the seed bank by prompt: refresh untouched seeded rows (this also
    // fixes "5² + 3³", which was seeded with no correct option), insert new ones.
    $seed = json_decode(file_get_contents(__DIR__ . '/../seeds/questions.json'), true, flags: JSON_THROW_ON_ERROR);
    $find = $db->prepare('SELECT id, updated_at FROM questions WHERE prompt = ? ORDER BY id LIMIT 1');
    $refresh = $db->prepare('UPDATE questions SET subject = ?, difficulty = ?, options = ?, answer_idx = ?, explanation = ? WHERE id = ?');
    $insert = $db->prepare('INSERT INTO questions (subject, difficulty, prompt, options, answer_idx, explanation) VALUES (?, ?, ?, ?, ?, ?)');
    $added = $updated = 0;
    foreach ($seed as $q) {
        $options = json_encode($q['options'], JSON_UNESCAPED_UNICODE);
        $find->execute([$q['prompt']]);
        $row = $find->fetch();
        if ($row === false) {
            $insert->execute([$q['subject'], $q['difficulty'], $q['prompt'], $options, $q['answer_idx'], $q['explanation']]);
            $added++;
        } elseif ($row['updated_at'] === null) {
            $refresh->execute([$q['subject'], $q['difficulty'], $options, $q['answer_idx'], $q['explanation'], $row['id']]);
            $updated++;
        }
    }
    $m->say("questions: $added added, $updated refreshed");

    // ---- Spaced repetition --------------------------------------------------
    // A wrong answer puts the question in box 1 (due now). Each later correct
    // answer moves it up a box with a longer interval; past the last box it's mastered.
    $db->exec("CREATE TABLE IF NOT EXISTS question_reviews (
        user_id INT NOT NULL,
        question_id INT NOT NULL,
        box TINYINT NOT NULL DEFAULT 1,
        due_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, question_id),
        KEY idx_due (user_id, due_at),
        CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_reviews_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    ) $opts");

    // ---- Runs -------------------------------------------------------------
    $m->addColumn('runs', 'mode', "ENUM('normal','daily','challenge') NOT NULL DEFAULT 'normal' AFTER user_id");
    $m->addColumn('runs', 'daily_date', 'DATE NULL AFTER mode');
    $m->addColumn('runs', 'challenge_of', 'INT NULL AFTER daily_date');
    $m->addColumn('runs', 'share_code', 'CHAR(8) NULL');
    $m->addColumn('runs', 'pickup_count', 'TINYINT NOT NULL DEFAULT 0');
    $m->addColumn('runs', 'pickups_mask', 'INT NOT NULL DEFAULT 0');
    $m->addColumn('runs', 'best_combo', 'INT NOT NULL DEFAULT 0');
    $m->addColumn('runs', 'reviews_served', 'TINYINT NOT NULL DEFAULT 0');
    $m->addColumn('runs', 'duration_seconds', 'INT NULL');
    // One daily attempt per player per day (NULLs don't collide, so normal runs are unaffected).
    if (!$m->indexExists('runs', 'uniq_daily')) $db->exec('ALTER TABLE runs ADD UNIQUE KEY uniq_daily (user_id, daily_date)');
    if (!$m->indexExists('runs', 'uniq_share')) $db->exec('ALTER TABLE runs ADD UNIQUE KEY uniq_share (share_code)');
    if (!$m->indexExists('runs', 'idx_daily_rank')) $db->exec('ALTER TABLE runs ADD KEY idx_daily_rank (daily_date, status, duration_seconds)');

    $db->exec("CREATE TABLE IF NOT EXISTS daily_mazes (
        day DATE PRIMARY KEY,
        seed INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) $opts");

    // ---- Weekly prizes ----------------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS weekly_settlements (
        week_start DATE PRIMARY KEY,
        settled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) $opts");
    $db->exec("CREATE TABLE IF NOT EXISTS weekly_rewards (
        week_start DATE NOT NULL,
        `rank` TINYINT NOT NULL,
        user_id INT NOT NULL,
        xp INT NOT NULL,
        coins INT NOT NULL,
        PRIMARY KEY (week_start, `rank`),
        KEY idx_user (user_id),
        CONSTRAINT fk_weekly_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");

    // ---- Achievements (codes instead of display names) --------------------
    $db->exec("CREATE TABLE IF NOT EXISTS user_achievements (
        user_id INT NOT NULL,
        code VARCHAR(40) NOT NULL,
        unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, code),
        CONSTRAINT fk_achievements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");
    if ($m->tableExists('user_badges')) {
        $db->exec("INSERT IGNORE INTO user_achievements (user_id, code, unlocked_at)
                   SELECT user_id, CASE badge_name WHEN 'Level 5' THEN 'level_5' WHEN 'Rich Explorer' THEN 'rich'
                                                   WHEN '10 Day Streak' THEN 'streak_10' END, awarded_at
                   FROM user_badges WHERE badge_name IN ('Level 5', 'Rich Explorer', '10 Day Streak')");
        $db->exec('RENAME TABLE user_badges TO legacy_user_badges');
        $m->say('badges moved to user_achievements (old table kept as legacy_user_badges)');
    }

    // ---- Cosmetics ----------------------------------------------------------
    $m->addColumn('users', 'equipped_skin', 'VARCHAR(40) NULL');
    $m->addColumn('users', 'equipped_theme', 'VARCHAR(40) NULL');
    // Before this, owning a skin applied it automatically (red over blue): keep that look.
    $db->exec("UPDATE users u SET equipped_skin = (
                 SELECT item_code FROM inventory i WHERE i.user_id = u.id AND i.item_code IN ('skin2', 'skin3') AND i.qty > 0
                 ORDER BY i.item_code DESC LIMIT 1)
               WHERE equipped_skin IS NULL");

    // ---- Shop ---------------------------------------------------------------
    $db->exec("ALTER TABLE items MODIFY kind ENUM('powerup','pack','skin','theme','perk') NOT NULL");
    $items = [
        // code, name, description, kind, price, grants, active, sort
        ['streakFreeze', 'Streak Freeze', 'Covers one missed day so your check-in streak survives (hold up to 2)', 'perk', 50, ['streakFreeze' => 1], 1, 65],
        ['forest',       'Forest Theme',  'Mossy greens for the maze board', 'theme', 300, ['forest' => 1], 1, 90],
        ['space',        'Space Theme',   'A starfield maze in deep blue',   'theme', 450, ['space' => 1], 1, 100],
    ];
    $up = $db->prepare('INSERT INTO items (code, name, description, icon, kind, price, grants, active, sort_order)
                        VALUES (?, ?, ?, \'\', ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), kind = VALUES(kind),
                          price = VALUES(price), grants = VALUES(grants), active = VALUES(active), sort_order = VALUES(sort_order)');
    foreach ($items as $i) {
        $i[5] = json_encode($i[5]);
        $up->execute($i);
    }
    $m->say('shop: streak freeze added, forest and space themes on sale');
};
