<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);

// Supports local dev (project/public) and shared-host deploy (project root as webroot).
if (!is_dir($baseDir . '/src') && is_dir(__DIR__ . '/src')) {
    $baseDir = __DIR__;
}

require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $result = $pdo->query('SELECT NOW() AS server_time')->fetch();
    
    // Get list of tables
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    
    // Test write (optional, controlled by ?test=write)
    $writeTest = null;
    if (($_GET['test'] ?? null) === 'write') {
        $stmt = $pdo->prepare('INSERT INTO households (name) VALUES (?)');
        $stmt->execute(['API Test ' . date('Y-m-d H:i:s')]);
        $id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare('SELECT id, name FROM households WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $writeTest = $stmt->fetch();
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Mad API is running (database fully operational)',
        'db_server_time' => $result['server_time'],
        'tables' => $tables,
        'tables_count' => count($tables),
        'test_write' => $writeTest,
        'endpoints' => [
            '/' => 'This status page',
            '/?test=write' => 'Test database write',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
