<?php

declare(strict_types=1);

// A deliberately small test kit (no Composer): named tests, assertions, an
// HTTP client with its own cookie jar per player, and database helpers for
// setting up state and skipping waits.
final class TestKit
{
    private int $passed = 0;
    private array $failures = [];
    private int $seq = 0;

    public function __construct(public readonly string $base, public readonly PDO $db, private ?string $filter) {}

    public function section(string $name): void
    {
        echo "\n$name\n";
    }

    public function test(string $name, callable $fn): void
    {
        if ($this->filter !== null && stripos($name, $this->filter) === false) return;
        try {
            $fn();
            $this->passed++;
            echo "  ✓ $name\n";
        } catch (Throwable $e) {
            $this->failures[] = [$name, $e];
            echo "  ✗ $name\n      " . str_replace("\n", "\n      ", $e->getMessage()) . "\n";
        }
    }

    public function report(string $serverLog): int
    {
        $total = $this->passed + count($this->failures);
        echo "\n" . ($this->failures ? 'FAILED' : 'OK') . ": {$this->passed}/$total passed\n";
        if ($this->failures && is_file($serverLog)) {
            // Drop the built-in server's per-request access lines; keep PHP errors.
            $lines = preg_grep('/ (Accepted|Closing)$|\]: (GET|POST) /', file($serverLog, FILE_IGNORE_NEW_LINES), PREG_GREP_INVERT);
            if ($lines) echo "\nServer errors (last lines):\n" . implode("\n", array_slice($lines, -15)) . "\n";
        }
        return $this->failures ? 1 : 0;
    }

    // ---- Assertions ------------------------------------------------------------------

    public function ok(mixed $cond, string $msg = 'expected true'): void
    {
        if (!$cond) throw new RuntimeException($msg);
    }

    public function eq(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    // Asserts an API call failed with this error code (and returns the body).
    public function fails(string $code, array $res, ?int $status = null): array
    {
        if (($res['body']['success'] ?? null) !== false || ($res['body']['code'] ?? null) !== $code) {
            throw new RuntimeException("expected error $code, got HTTP {$res['status']} " . json_encode($res['body']));
        }
        if ($status !== null) $this->eq($status, $res['status'], "HTTP status for $code");
        return $res['body'];
    }

    // ---- Players -----------------------------------------------------------------------

    // Registers and logs in a new player; returns a client bound to their session.
    public function player(string $prefix = 'p'): Client
    {
        $name = $prefix . (++$this->seq) . substr(bin2hex(random_bytes(3)), 0, 4);
        $c = new Client($this->base);
        $c->ok('POST', '/api/auth/register', ['username' => $name, 'email' => "$name@test.local", 'password' => 'secret123', 'confirm' => 'secret123']);
        $c->ok('POST', '/api/auth/login', ['email' => "$name@test.local", 'password' => 'secret123']);
        $c->username = $name;
        $c->id = (int) $this->scalar('SELECT id FROM users WHERE username = ?', [$name]);
        return $c;
    }

    public function admin(): Client
    {
        $name = 'admin' . (++$this->seq);
        $this->db->prepare('INSERT INTO admin (fullname, email, username, password) VALUES (?, ?, ?, ?)')
                 ->execute([$name, "$name@test.local", $name, password_hash('adminpass1', PASSWORD_DEFAULT)]);
        $c = new Client($this->base);
        $c->ok('POST', '/api/auth/admin-login', ['email' => "$name@test.local", 'password' => 'adminpass1']);
        return $c;
    }

    // ---- Database helpers ---------------------------------------------------------------

    public function scalar(string $sql, array $params = []): mixed
    {
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return $s->fetchColumn();
    }

    public function exec(string $sql, array $params = []): void
    {
        $this->db->prepare($sql)->execute($params);
    }

    public function answerIdx(int $questionId): int
    {
        return (int) $this->scalar('SELECT answer_idx FROM questions WHERE id = ?', [$questionId]);
    }

    // Skip the server's anti-cheat waits instead of sleeping in tests.
    public function ageQuestion(int $runId, int $gateNo): void
    {
        $this->exec('UPDATE run_gates SET served_at = NOW(3) - INTERVAL 5 SECOND WHERE run_id = ? AND gate_no = ?', [$runId, $gateNo]);
    }

    public function ageRun(int $runId, int $seconds = 120): void
    {
        $this->exec('UPDATE runs SET started_at = started_at - INTERVAL ? SECOND WHERE id = ?', [$seconds, $runId]);
    }

    // Asks for gate N's question and answers it (correctly unless told otherwise).
    // Returns [question, answer response].
    public function answerGate(Client $c, int $runId, int $gateNo, bool $correct = true): array
    {
        $q = $c->ok('POST', '/api/runs/question', ['run_id' => $runId, 'gate_no' => $gateNo]);
        $this->ageQuestion($runId, $gateNo);
        $right = $this->answerIdx($q['question_id']);
        $pick = $correct ? $right : ($right + 1) % count($q['options']);
        $a = $c->ok('POST', '/api/runs/answer', ['run_id' => $runId, 'gate_no' => $gateNo, 'attempt_no' => $q['attempt_no'], 'option' => $pick]);
        return [$q, $a];
    }

    // Clears every gate correctly and finishes the run.
    public function finishRun(Client $c, array $run, int $ageSeconds = 120): array
    {
        foreach ($run['gates'] as $g) {
            if ($g['status'] === 'pending') $this->answerGate($c, $run['run_id'], $g['gate_no']);
        }
        $this->ageRun($run['run_id'], $ageSeconds);
        return $c->ok('POST', '/api/runs/complete', ['run_id' => $run['run_id']]);
    }

    // Coins and XP on the users row must equal the sum of the player's ledger.
    public function assertLedgerBalanced(int $userId): void
    {
        $u = $this->db->query("SELECT coins, xp FROM users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
        $l = $this->db->query("SELECT COALESCE(SUM(coins_delta),0) c, COALESCE(SUM(xp_delta),0) x FROM ledger WHERE user_id = $userId")->fetch(PDO::FETCH_ASSOC);
        $this->eq([(int) $u['coins'], (int) $u['xp']], [(int) $l['c'], (int) $l['x']], "ledger vs balance for user $userId");
    }
}

final class Client
{
    public string $username = '';
    public int $id = 0;
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'mq-cookies');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{status: int, body: mixed, headers: string} */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->base . $path;
        if ($method === 'GET' && $body) $url .= (str_contains($path, '?') ? '&' : '?') . http_build_query($body);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? new stdClass()));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        $raw = curl_exec($ch);
        if ($raw === false) throw new RuntimeException("$method $path: " . curl_error($ch));
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $text = substr($raw, $headerSize);
        $json = json_decode($text, true);
        return ['status' => $status, 'body' => $json ?? $text, 'headers' => substr($raw, 0, $headerSize)];
    }

    // A call that must succeed; returns the JSON body.
    public function ok(string $method, string $path, ?array $body = null): array
    {
        $res = $this->request($method, $path, $body);
        if (!is_array($res['body']) || ($res['body']['success'] ?? false) !== true) {
            throw new RuntimeException("$method $path → HTTP {$res['status']}: " . (is_array($res['body']) ? json_encode($res['body']) : substr((string) $res['body'], 0, 300)));
        }
        return $res['body'];
    }
}
