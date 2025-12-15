-- ======================================================
--  MAZEQUEST DATABASE SCHEMA (FULL & CLEAN)
--  All sample data is included but COMMENTED OUT  
-- ======================================================

CREATE DATABASE IF NOT EXISTS mazequest;
USE mazequest;

-- ======================================================
-- 1. USERS TABLE
-- ======================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    coins INT DEFAULT 0,
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    streak INT DEFAULT 0,
    avatar VARCHAR(255) DEFAULT NULL,
    last_checkin DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    login_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sample user (commented)
-- INSERT INTO users (username, email, password, role, coins, level, xp)
-- VALUES ('testuser', 'user@example.com', PASSWORD('test1234'), 'user', 100, 1, 0);


-- ======================================================
-- 2. ADMIN TABLE
-- ======================================================
CREATE TABLE admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Sample admin (commented)
-- INSERT INTO admin (fullname, email, username, password)
-- VALUES ('Administrator', 'admin@example.com', 'admin', PASSWORD('admin1234'));


-- ======================================================
-- 3. PLAYER TABLE
-- ======================================================
CREATE TABLE player (
    player_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255),
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE,
    contact VARCHAR(50),
    coins INT DEFAULT 0,
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    total_score INT DEFAULT 0
);

-- Sample player (commented)
-- INSERT INTO player (fullname, username, email, coins, level, xp)
-- VALUES ('Test Player', 'player1', 'player@example.com', 50, 1, 0);


-- ======================================================
-- 4. LEADERBOARD TABLE
-- ======================================================
CREATE TABLE leaderboard (
    leaderboard_id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    total_score INT DEFAULT 0,
    rank INT DEFAULT NULL,
    FOREIGN KEY (player_id) REFERENCES player(player_id) ON DELETE CASCADE
);

-- Sample leaderboard row (commented)
-- INSERT INTO leaderboard (player_id, total_score, rank) VALUES (1, 500, 1);


-- ======================================================
-- 5. MAZE TABLE
-- ======================================================
CREATE TABLE maze (
    maze_id INT AUTO_INCREMENT PRIMARY KEY,
    maze_name VARCHAR(255),
    maze_level INT DEFAULT 1,
    difficulty_level VARCHAR(50),
    description TEXT,
    created_by_admin_id INT,
    FOREIGN KEY (created_by_admin_id) REFERENCES admin(admin_id)
);

-- Sample maze (commented)
-- INSERT INTO maze (maze_name, maze_level, difficulty_level, description, created_by_admin_id)
-- VALUES ('Forest Maze', 1, 'Easy', 'First level maze', 1);


-- ======================================================
-- 6. GAME SESSION TABLE
-- ======================================================
CREATE TABLE game_session (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT,
    maze_id INT,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME DEFAULT NULL,
    score_earned INT DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES player(player_id),
    FOREIGN KEY (maze_id) REFERENCES maze(maze_id)
);

-- Sample session (commented)
-- INSERT INTO game_session (player_id, maze_id, score_earned)
-- VALUES (1, 1, 120);


-- ======================================================
-- 7. USER BADGES TABLE
-- ======================================================
CREATE TABLE user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_name VARCHAR(255) NOT NULL,
    awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample badge (commented)
-- INSERT INTO user_badges (user_id, badge_name) VALUES (1, 'First Login');


-- ======================================================
-- 8. GAMES TABLE
-- ======================================================
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_name VARCHAR(255),
    description TEXT,
    reward INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sample game (commented)
-- INSERT INTO games (game_name, description, reward)
-- VALUES ('Memory Match', 'Match the cards to win', 100);


-- ======================================================
-- 9. REWARDS TABLE
-- ======================================================
CREATE TABLE rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    type VARCHAR(50),          -- coins / xp / item
    value INT DEFAULT 0
);

-- Sample rewards (commented)
-- INSERT INTO rewards (name, type, value) VALUES 
-- ('Small Coin Pack', 'coins', 20),
-- ('Big Coin Pack', 'coins', 50),
-- ('XP Boost', 'xp', 30);

