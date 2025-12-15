<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mazequest";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if (!$conn->query($sql)) {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($dbname);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    coins INT DEFAULT 0,
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    streak INT DEFAULT 0,
    badges TEXT,
    powerups TEXT,
    role ENUM('user','admin') DEFAULT 'user',
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    die("Error creating users table: " . $conn->error);
}

// Create admin user if not exists
$admin_check = $conn->query("SELECT * FROM users WHERE username='admin'");
if ($admin_check->num_rows == 0) {
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password, email, coins, level, xp, streak, role, is_admin) 
            VALUES ('admin', '$hashed_password', 'admin@mazequest.com', 5000, 10, 1000, 30, 'admin', TRUE)";
    if (!$conn->query($sql)) {
        die("Error creating admin user: " . $conn->error);
    }
}

$conn->close();

// Function to get database connection
function getDBConnection() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "mazequest";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

?>