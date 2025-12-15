<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mazequest";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "✅ Database connected successfully!<br>";
}

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "✅ Users table exists!<br>";
    
    // Count users
    $count_result = $conn->query("SELECT COUNT(*) as total FROM users");
    $count = $count_result->fetch_assoc();
    echo "✅ Total users: " . $count['total'] . "<br>";
} else {
    echo "❌ Users table does NOT exist!<br>";
}

$conn->close();
?>