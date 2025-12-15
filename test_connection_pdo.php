<?php
header('Content-Type: text/plain');
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

try {
    echo "✅ PDO connected successfully\n";
    // Simple sanity check
    $stmt = $db->query("SELECT 1");
    echo "Test query result: ".$stmt->fetchColumn()."\n";

    // Check users table
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        echo "✅ Users table exists\n";
        $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "Total users: $count\n";
    } else {
        echo "❌ Users table not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error: ".$e->getMessage()."\n";
}
