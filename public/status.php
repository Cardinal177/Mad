<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    
    // Get list of tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Test write
    $stmt = $pdo->prepare('INSERT INTO households (name) VALUES (?)');
    $stmt->execute(['Test Husstand ' . date('Y-m-d H:i:s')]);
    $householdId = $pdo->lastInsertId();
    
    // Read back
    $stmt = $pdo->prepare('SELECT id, name FROM households WHERE id = ? LIMIT 1');
    $stmt->execute([$householdId]);
    $record = $stmt->fetch();
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Database fully operational (read + write)',
        'tables_count' => count($tables),
        'tables' => $tables,
        'test_write' => $record,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error',
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
