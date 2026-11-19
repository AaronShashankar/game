<?php

declare(strict_types=1);

namespace App\Support;

// Thrown anywhere in a request to produce a JSON error:
//   {"success": false, "code": "<CODE>", "message": "<message>"}
final class ApiError extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $code, string $message): self   { return new self(422, $code, $message); }
    public static function unauthorized(string $message = 'Not authenticated'): self { return new self(401, 'UNAUTHENTICATED', $message); }
    public static function forbidden(string $message = 'Forbidden'): self     { return new self(403, 'FORBIDDEN', $message); }
    public static function notFound(string $message = 'Not found'): self      { return new self(404, 'NOT_FOUND', $message); }
    public static function conflict(string $code, string $message): self     { return new self(409, $code, $message); }
    public static function tooMany(string $message): self                    { return new self(429, 'RATE_LIMITED', $message); }
}
