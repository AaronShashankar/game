<?php
// Test runner. Builds a throwaway database, starts PHP's built-in server
// against it, and runs every tests/*Test.php file over real HTTP.
//
//   docker compose exec web php tests/run.php            (Docker)
//   php tests/run.php                                    (XAMPP / local PHP)
//   php tests/run.php --filter=daily                     only tests whose name matches
//
// Environment (all optional):
//   TEST_DB_NAME        database to (re)create        default mazequest_test
//   TEST_DB_ADMIN_USER  account allowed to create it   default root
//   TEST_DB_ADMIN_PASS  its password                   default: DB_PASS when DB_USER is root, else "root"
//   TEST_PORT           built-in server port           default 8099
//
// The development database is never touched.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/lib/TestKit.php';

$testDb = env('TEST_DB_NAME', 'mazequest_test');
if ($testDb === env('DB_NAME', 'mazequest')) {
    fwrite(STDERR, "TEST_DB_NAME must differ from DB_NAME ($testDb): the runner drops and recreates it.\n");
    exit(1);
}
$port = (int) env('TEST_PORT', '8099');
$filter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--filter=')) $filter = substr($arg, 9);
}

// ---- 1. Fresh database ------------------------------------------------------------
$adminUser = env('TEST_DB_ADMIN_USER', 'root');
$adminPass = env('TEST_DB_ADMIN_PASS', env('DB_USER', 'root') === 'root' ? (env('DB_PASS') ?? '') : 'root');
try {
    $root = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', config('db.host'), config('db.port')), $adminUser, $adminPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, "Can't connect as $adminUser to create the test database: {$e->getMessage()}\nSet TEST_DB_ADMIN_USER / TEST_DB_ADMIN_PASS.\n");
    exit(1);
}
$root->exec("DROP DATABASE IF EXISTS `$testDb`");
$root->exec("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$appUser = config('db.user');
if ($appUser !== $adminUser) {
    $root->exec("GRANT ALL PRIVILEGES ON `$testDb`.* TO " . $root->quote($appUser) . "@'%'");
}

$env = ['DB_NAME' => $testDb, 'APP_DEBUG' => 'true', 'BASE_PATH' => ''] + getenv();

$out = [];
exec('DB_NAME=' . escapeshellarg($testDb) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(APP_ROOT . '/database/migrate.php') . ' 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "Migrations failed:\n" . implode("\n", $out) . "\n");
    exit(1);
}

// ---- 2. Server ----------------------------------------------------------------------
$log = sys_get_temp_dir() . '/mazequest-test-server.log';
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', APP_ROOT . '/public', APP_ROOT . '/public/index.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
    $pipes, APP_ROOT, $env,
);
register_shutdown_function(function () use ($server) {
    proc_terminate($server);
    proc_close($server);
});
for ($i = 0; $i < 50; $i++) {
    if (@fsockopen('127.0.0.1', $port)) break;
    usleep(100_000);
}

// ---- 3. Tests -----------------------------------------------------------------------
// An explicit connection to the test database (config() has already cached DB_NAME).
$testPdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', config('db.host'), config('db.port'), $testDb),
                   config('db.user'), config('db.pass'),
                   [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$kit = new TestKit("http://127.0.0.1:$port", $testPdo, $filter);
$files = glob(__DIR__ . '/*Test.php');
sort($files);
foreach ($files as $file) {
    $kit->section(basename($file, '.php'));
    (require $file)($kit);
}
exit($kit->report($log));
