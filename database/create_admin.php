<?php
// Create (or reset the password of) an admin account.
//   php database/create_admin.php <email> <username> <password>

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

[$_, $email, $username, $password] = array_pad($argv, 4, null);
if (!$email || !$username || !$password) {
    fwrite(STDERR, "Usage: php database/create_admin.php <email> <username> <password>\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$db = App\Support\Database::connection();
$db->prepare('INSERT INTO admin (fullname, email, username, password) VALUES (?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE password = VALUES(password)')
   ->execute([$username, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);
echo "Admin '$username' <$email> is ready.\n";
