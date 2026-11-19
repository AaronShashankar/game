<?php
// Core schema. Safe on a fresh database and on an existing MazeQuest database
// (existing tables get missing columns/indexes added).

return function (PDO $db, Migrator $m): void {
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    // ---- Players -------------------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        coins INT NOT NULL DEFAULT 0,
        xp INT NOT NULL DEFAULT 0,
        level INT NOT NULL DEFAULT 1,
        streak INT NOT NULL DEFAULT 0,
        avatar MEDIUMTEXT NULL,
        last_checkin DATE NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login DATETIME NULL,
        login_count INT NOT NULL DEFAULT 0,
        total_games INT NOT NULL DEFAULT 0,
        correct_answers INT NOT NULL DEFAULT 0,
        wrong_answers INT NOT NULL DEFAULT 0,
        powerups_used INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_username (username),
        UNIQUE KEY uniq_email (email),
        KEY idx_xp (xp),
        KEY idx_coins (coins)
    ) $opts");

    // Existing installs: bring older users tables up to date.
    foreach ([
        'last_checkin'    => 'DATE NULL',
        'is_active'       => 'TINYINT(1) NOT NULL DEFAULT 1',
        'last_login'      => 'DATETIME NULL',
        'login_count'     => 'INT NOT NULL DEFAULT 0',
        'total_games'     => 'INT NOT NULL DEFAULT 0',
        'correct_answers' => 'INT NOT NULL DEFAULT 0',
        'wrong_answers'   => 'INT NOT NULL DEFAULT 0',
        'powerups_used'   => 'INT NOT NULL DEFAULT 0',
        'created_at'      => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ] as $col => $def) {
        $m->addColumn('users', $col, $def);
    }
    // Avatars can be uploaded images (data URLs), which don't fit VARCHAR(255).
    $db->exec('ALTER TABLE users MODIFY avatar MEDIUMTEXT NULL');
    if (!$m->indexExists('users', 'idx_xp'))    $db->exec('ALTER TABLE users ADD KEY idx_xp (xp)');
    if (!$m->indexExists('users', 'idx_coins')) $db->exec('ALTER TABLE users ADD KEY idx_coins (coins)');

    // ---- Admins ----------------------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS admin (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(255) NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    ) $opts");

    // ---- Questions (moved out of the game's JavaScript) -------------------
    $db->exec("CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject VARCHAR(100) NOT NULL,
        prompt VARCHAR(500) NOT NULL,
        options JSON NOT NULL,
        answer_idx TINYINT NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_subject (subject, active)
    ) $opts");

    // ---- Runs: one maze attempt -------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        difficulty VARCHAR(10) NOT NULL,
        subject VARCHAR(100) NOT NULL,
        seed INT UNSIGNED NOT NULL,
        gate_count TINYINT NOT NULL,
        status ENUM('active','completed','abandoned') NOT NULL DEFAULT 'active',
        started_at DATETIME NOT NULL,
        deadline_at DATETIME NULL,
        completed_at DATETIME NULL,
        combo INT NOT NULL DEFAULT 0,
        doubler_pending TINYINT(1) NOT NULL DEFAULT 0,
        freeze_until DATETIME NULL,
        coins_earned INT NOT NULL DEFAULT 0,
        xp_earned INT NOT NULL DEFAULT 0,
        KEY idx_user_started (user_id, started_at),
        KEY idx_status (status),
        CONSTRAINT fk_runs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");

    $db->exec("CREATE TABLE IF NOT EXISTS run_gates (
        run_id INT NOT NULL,
        gate_no TINYINT NOT NULL,
        question_id INT NULL,
        status ENUM('pending','cleared','skipped') NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        served_at DATETIME(3) NULL,
        cleared_at DATETIME NULL,
        PRIMARY KEY (run_id, gate_no),
        CONSTRAINT fk_gates_run FOREIGN KEY (run_id) REFERENCES runs(id) ON DELETE CASCADE
    ) $opts");

    // Every answer ever given. The primary key doubles as the idempotency key
    // for client retries.
    $db->exec("CREATE TABLE IF NOT EXISTS run_answers (
        run_id INT NOT NULL,
        gate_no TINYINT NOT NULL,
        attempt_no INT NOT NULL,
        question_id INT NOT NULL,
        chosen_idx TINYINT NOT NULL,
        correct TINYINT(1) NOT NULL,
        coins_delta INT NOT NULL DEFAULT 0,
        xp_delta INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (run_id, gate_no, attempt_no),
        KEY idx_question (question_id),
        CONSTRAINT fk_answers_run FOREIGN KEY (run_id) REFERENCES runs(id) ON DELETE CASCADE
    ) $opts");

    // ---- Ledger: every coin/XP change -------------------------------------
    // UNIQUE(user_id, reason, ref) guarantees each reward is paid at most once.
    $db->exec("CREATE TABLE IF NOT EXISTS ledger (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        coins_delta INT NOT NULL DEFAULT 0,
        xp_delta INT NOT NULL DEFAULT 0,
        reason VARCHAR(32) NOT NULL,
        ref VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_award (user_id, reason, ref),
        KEY idx_user_time (user_id, created_at),
        KEY idx_time (created_at),
        CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");

    // ---- Shop ---------------------------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS items (
        code VARCHAR(40) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        icon VARCHAR(16) NOT NULL DEFAULT '',
        kind ENUM('powerup','pack','skin','theme') NOT NULL,
        price INT NOT NULL,
        grants JSON NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0
    ) $opts");

    $db->exec("CREATE TABLE IF NOT EXISTS inventory (
        user_id INT NOT NULL,
        item_code VARCHAR(40) NOT NULL,
        qty INT NOT NULL DEFAULT 0,
        PRIMARY KEY (user_id, item_code),
        CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");

    // ---- Badges -------------------------------------------------------------
    $db->exec("CREATE TABLE IF NOT EXISTS user_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        badge_name VARCHAR(100) NOT NULL,
        awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_badge (user_id, badge_name),
        CONSTRAINT fk_badges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");
    if (!$m->indexExists('user_badges', 'uniq_badge')) {
        $db->exec('DELETE b1 FROM user_badges b1 JOIN user_badges b2
                   ON b1.user_id = b2.user_id AND b1.badge_name = b2.badge_name AND b1.id > b2.id');
        $db->exec('ALTER TABLE user_badges ADD UNIQUE KEY uniq_badge (user_id, badge_name)');
    }

    // ---- Security log (logins, rejected game events) ----------------------
    $db->exec("CREATE TABLE IF NOT EXISTS security_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        event VARCHAR(50) NOT NULL,
        username VARCHAR(190) NULL,
        ip VARCHAR(45) NULL,
        detail VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_event_time (event, created_at),
        KEY idx_ip_time (ip, created_at)
    ) $opts");
};
