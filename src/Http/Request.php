<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\ApiError;

// Unified access to JSON body, form fields and query string.
final class Request
{
    private array $input;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
    ) {
        $json = [];
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $json = is_array($decoded) ? $decoded : [];
        }
        $this->input = array_merge($_GET, $_POST, $json);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->get($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, ?int $default = null): int
    {
        $v = $this->get($key, $default);
        if ($v === null || !is_numeric($v)) {
            throw ApiError::badRequest('INVALID_INPUT', "'$key' must be a number");
        }
        return (int) $v;
    }

    public function required(string $key): string
    {
        $v = $this->string($key);
        if ($v === '') throw ApiError::badRequest('INVALID_INPUT', "'$key' is required");
        return $v;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
