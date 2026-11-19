<?php
// Shared bootstrap for the web front controller and CLI scripts.

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// PSR-4 style autoloader: App\Foo\Bar -> src/Foo/Bar.php (no Composer needed).
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) return;
    $file = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

// Minimal .env loader: KEY=value lines, # comments, optional quotes.
(function (): void {
    $file = APP_ROOT . '/.env';
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (getenv($k) === false) putenv("$k=$v");
    }
})();

require APP_ROOT . '/src/Support/helpers.php';

// Errors go to the log, never into HTML/JSON output.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
