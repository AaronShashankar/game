<?php
session_start();
header('Content-Type: application/json');

// लगइन जाँच गर्नुहोस्
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(["success" => false, "message" => "User not logged in."]);
    exit;
}

require_once __DIR__ . '/../Config/database.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$base_bonus = 100; // आधारभूत दैनिक बोनस

$sql_fetch = "SELECT last_checkin, streak, coins FROM users WHERE id = :id";
$stmt_fetch = $db->prepare($sql_fetch);
$stmt_fetch->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt_fetch->execute();
$user_data = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

$last_checkin = $user_data['last_checkin'];
$current_streak = $user_data['streak'];
$current_coins = $user_data['coins'];
$update_required = false;
$message = "No daily check-in bonus. Already checked in today.";

// १. आजको मिति जाँच गर्नुहोस्
if ($last_checkin != $today) {
    $update_required = true;
    
    // २. स्ट्रीक जाँच गर्नुहोस्
    if ($last_checkin == $yesterday) {
        // स्ट्रीक जारी छ
        $current_streak++;
        $streak_bonus = $current_streak * 10;
        $coins_to_add = $base_bonus + $streak_bonus;
        $message = "Daily check-in successful! Streak continues: $current_streak days. You earned $coins_to_add coins!";
    } else {
        // स्ट्रीक टुट्यो (Reset)
        $current_streak = 1;
        $coins_to_add = $base_bonus;
        $message = "Daily check-in successful! New streak started (1 day). You earned $coins_to_add coins!";
    }
    
    // ३. सिक्का अपडेट गर्नुहोस्
    $new_coins = $current_coins + $coins_to_add;
} else {
    // आजको लागि पहिले नै चेक-इन गरिएको छ
    $new_coins = $current_coins;
    $coins_to_add = 0;
}

// ४. डाटाबेस अपडेट गर्नुहोस् यदि आवश्यक भएमा
if ($update_required) {
    $sql_update = "UPDATE users SET last_checkin = :today, streak = :streak, coins = :coins WHERE id = :id";
    $stmt_update = $db->prepare($sql_update);
    $stmt_update->bindParam(':today', $today);
    $stmt_update->bindParam(':streak', $current_streak, PDO::PARAM_INT);
    $stmt_update->bindParam(':coins', $new_coins, PDO::PARAM_INT);
    $stmt_update->bindParam(':id', $user_id, PDO::PARAM_INT);
    
    if ($stmt_update->execute()) {
        // Session डाटा अपडेट गर्नुहोस्
        $_SESSION['last_checkin'] = $today;
        $_SESSION['streak'] = $current_streak;
        $_SESSION['coins'] = $new_coins;

        echo json_encode(["success" => true, "message" => $message, "coins_earned" => $coins_to_add, "new_streak" => $current_streak]);
    } else {
        echo json_encode(["success" => false, "message" => "Database update failed."]);
    }
} else {
    echo json_encode(["success" => true, "message" => $message]);
}

// PDO cleanup automatic
?>
