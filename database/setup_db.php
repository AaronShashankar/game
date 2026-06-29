<?php
require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();

// Create Admin in `admin` table
$adminHash = password_hash('admin', PASSWORD_DEFAULT);
$db->prepare("INSERT IGNORE INTO admin (fullname, email, username, password) VALUES ('Administrator', 'admin@mazequest.com', 'admin', :p)")->execute([':p' => $adminHash]);

// Create Admin in `users` table (since old login.php uses it)
$db->prepare("INSERT IGNORE INTO users (username, email, password, role, coins, level, xp) VALUES ('admin', 'admin@mazequest.com', :p, 'admin', 100, 1, 0)")->execute([':p' => $adminHash]);

// Create User in `users` table
$userHash = password_hash('user', PASSWORD_DEFAULT);
$db->prepare("INSERT IGNORE INTO users (username, email, password, role, coins, level, xp) VALUES ('user', 'user@mazequest.com', :p, 'user', 100, 1, 0)")->execute([':p' => $userHash]);

echo "Database seeded successfully.\n";
