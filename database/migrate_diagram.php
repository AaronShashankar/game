<?php
// migrate_diagram.php
// Executes database/schema_diagram.sql using existing PDO connection

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $sqlPath = __DIR__ . '/database/schema_diagram.sql';
    if (!file_exists($sqlPath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'schema_diagram.sql not found']);
        exit;
    }

    $sql = file_get_contents($sqlPath);

    $db = (new Database())->getConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Split statements carefully by semicolon while keeping engine-safe IF NOT EXISTS blocks simple
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

    $db->beginTransaction();
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $db->exec($stmt);
    }
    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Schema migration completed']);
} catch (Throwable $e) {
    if (isset($db) && $db && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
