<?php
// migrate_schema.php
// Run this once to ensure required tables/columns exist

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Create users table if missing (with all required columns)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(191) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user','admin') NOT NULL DEFAULT 'user',
        coins INT NOT NULL DEFAULT 100,
        level INT NOT NULL DEFAULT 1,
        xp INT NOT NULL DEFAULT 0,
        streak INT NOT NULL DEFAULT 0,
        avatar VARCHAR(255) NULL,
        last_checkin DATE NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login DATETIME NULL,
        login_count INT NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_email (email),
        UNIQUE KEY uniq_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Users: add missing columns used by the app
    $alterStatements = [
        "ALTER TABLE users ADD COLUMN last_checkin DATE NULL",
        "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL",
        "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL",
        "ALTER TABLE users ADD COLUMN last_login DATETIME NULL",
        "ALTER TABLE users ADD COLUMN login_count INT NOT NULL DEFAULT 0",
        "ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL",
        "ALTER TABLE users ADD COLUMN total_games INT NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN correct_answers INT NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN wrong_answers INT NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN powerups_used INT NOT NULL DEFAULT 0"
    ];

    foreach ($alterStatements as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* ignore if exists */ }
    }

    // Rewards table
    $db->exec("CREATE TABLE IF NOT EXISTS rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type ENUM('coins','item') NOT NULL DEFAULT 'coins',
        value INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Games table
    $db->exec("CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        score INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Game sessions table (used by leaderboard.php for games_played)
    $db->exec("CREATE TABLE IF NOT EXISTS game_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        score INT NOT NULL DEFAULT 0,
        xp_earned INT NOT NULL DEFAULT 0,
        coins_earned INT NOT NULL DEFAULT 0,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // User achievements table (optional badges displayed in leaderboard)
    $db->exec("CREATE TABLE IF NOT EXISTS user_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        achievement_name VARCHAR(100) NOT NULL,
        earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // User badges (simple mapping)
    $db->exec("CREATE TABLE IF NOT EXISTS user_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        badge_name VARCHAR(100) NOT NULL,
        awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // User achievements (used in leaderboard)
    $db->exec("CREATE TABLE IF NOT EXISTS user_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        achievement_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Game sessions (used in leaderboard subquery)
    $db->exec("CREATE TABLE IF NOT EXISTS game_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ended_at TIMESTAMP NULL,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo json_encode([ 'success' => true, 'message' => 'Migration completed' ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([ 'success' => false, 'error' => $e->getMessage() ]);
}
