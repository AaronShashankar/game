<?php
// Applies database/migrations/*.php in order, once each.
//   php database/migrate.php            apply pending migrations
//   php database/migrate.php --status   list applied / pending
//
// Each migration file returns function (PDO $db, Migrator $m): void.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Support\Database;

final class Migrator
{
    public function __construct(private PDO $db) {}

    public function tableExists(string $table): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $s->execute([$table]);
        return (bool) $s->fetchColumn();
    }

    public function columnExists(string $table, string $column): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $s->execute([$table, $column]);
        return (bool) $s->fetchColumn();
    }

    public function indexExists(string $table, string $index): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $s->execute([$table, $index]);
        return (bool) $s->fetchColumn();
    }

    // Portable "ADD COLUMN IF NOT EXISTS" (MySQL 8 doesn't support the syntax).
    public function addColumn(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    public function say(string $msg): void
    {
        fwrite(STDOUT, "    $msg\n");
    }
}

$db = Database::connection();
$m = new Migrator($db);

$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    name VARCHAR(190) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = $db->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.php');
sort($files);

if (in_array('--status', $argv, true)) {
    foreach ($files as $f) {
        $name = basename($f, '.php');
        printf("%-10s %s\n", in_array($name, $applied, true) ? 'applied' : 'PENDING', $name);
    }
    exit(0);
}

$ran = 0;
foreach ($files as $file) {
    $name = basename($file, '.php');
    if (in_array($name, $applied, true)) continue;
    echo "→ $name\n";
    $migration = require $file;
    // DDL auto-commits in MySQL, so migrations are written to be re-runnable
    // (IF NOT EXISTS / existence checks) rather than relying on a transaction.
    $migration($db, $m);
    $db->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
    $ran++;
}
echo $ran ? "Done: $ran migration(s) applied.\n" : "Nothing to migrate.\n";
