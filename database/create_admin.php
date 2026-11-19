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
$hash = password_hash($password, PASSWORD_DEFAULT);

// Only ever touch the account that matches BOTH email and username. If either
// one belongs to a different admin, stop rather than overwrite that account.
$s = $db->prepare('SELECT email, username FROM admin WHERE email = ? OR username = ?');
$s->execute([$email, $username]);
$matches = $s->fetchAll();

if (!$matches) {
    $db->prepare('INSERT INTO admin (fullname, email, username, password) VALUES (?, ?, ?, ?)')
       ->execute([$username, $email, $username, $hash]);
    echo "Created admin '$username' <$email>.\n";
    exit(0);
}
if (count($matches) === 1 && strcasecmp($matches[0]['email'], $email) === 0 && $matches[0]['username'] === $username) {
    $db->prepare('UPDATE admin SET password = ? WHERE email = ?')->execute([$hash, $email]);
    echo "Reset the password for admin '$username' <$email>.\n";
    exit(0);
}
foreach ($matches as $m) {
    fwrite(STDERR, "Conflict: admin '{$m['username']}' <{$m['email']}> already uses that email or username.\n");
}
fwrite(STDERR, "Nothing changed. To reset a password, pass that admin's exact email and username.\n");
exit(1);
