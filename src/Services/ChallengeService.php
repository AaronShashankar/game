<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RunRepository;

// "Challenge a friend": a finished maze gets a share code. A friend who opens
// the link plays the same layout with the same questions and their time is
// compared with the original.
final class ChallengeService
{
    // No 0/O/1/I/L so codes survive being read aloud or retyped.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const CODE_LENGTH = 8;

    public function __construct(private RunRepository $runs = new RunRepository()) {}

    // Gives a completed run a unique share code (retrying on the rare collision).
    public function assignCode(int $runId): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = self::randomCode();
            if ($this->runs->setShareCode($runId, $code)) return $code;
        }
        throw new \RuntimeException('Could not allocate a share code');
    }

    public static function isValidCode(string $code): bool
    {
        return (bool) preg_match('/^[' . self::ALPHABET . ']{' . self::CODE_LENGTH . '}$/', $code);
    }

    // Public details of a challenge, for the /challenge page. Null if unknown.
    public function info(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!self::isValidCode($code)) return null;
        $run = $this->runs->findByShareCode($code);
        if ($run === null) return null;
        return [
            'code'             => $code,
            'username'         => $run['username'],
            'difficulty'       => $run['difficulty'],
            'subject'          => $run['subject'],
            'gate_count'       => (int) $run['gate_count'],
            'duration_seconds' => (int) $run['duration_seconds'],
        ];
    }

    private static function randomCode(): string
    {
        $out = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        return $out;
    }
}
