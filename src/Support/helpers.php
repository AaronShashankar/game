<?php
// Global helpers available to views, controllers and CLI scripts.

declare(strict_types=1);

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require APP_ROOT . '/config/app.php';
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

// URL prefix the app is served under ("" or e.g. "/game").
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $configured = config('base_path');
    if ($configured !== null) {
        return $base = rtrim($configured, '/');
    }
    // SCRIPT_NAME is /game/public/index.php (htdocs install) or /index.php (DocumentRoot=public).
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/public$#', '', $dir);
    return $base = rtrim($dir, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    // Cache-bust with the file's mtime so browsers pick up new JS/CSS.
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . ltrim($path, '/')) . $v;
}

// HTML-escape for views.
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

// Render a view from views/ with the given variables in scope.
function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/views/' . $name . '.php';
}

// Script tags every page needs before its own JS: app config, API client,
// shared layout behaviour (theme, logout).
function app_scripts(): string
{
    $cfg = json_encode(['base' => base_path()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return "<script>window.App = {$cfg};</script>\n"
         . '  <script src="' . e(asset('js/lib/api.js')) . "\"></script>\n"
         . '  <script src="' . e(asset('js/layout.js')) . '"></script>';
}

// <script> tag for a page's own JS file in public/assets/js/.
function page_script(string $name): string
{
    return '<script src="' . e(asset("js/$name.js")) . '"></script>';
}
