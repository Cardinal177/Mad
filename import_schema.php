<?php

declare(strict_types=1);

require_once 'src/bootstrap.php';
require_once 'config/database.php';

$schema = file_get_contents('sql/schema.sql');
$pdo = db();

// Split by semicolon and execute each statement
$statements = array_filter(
    array_map('trim', explode(';', $schema)),
    fn ($s) => $s && !str_starts_with($s, '--') && !str_starts_with($s, '/*')
);

foreach ($statements as $stmt_str) {
    try {
        $pdo->exec($stmt_str);
        echo 'Executed: ' . substr($stmt_str, 0, 60) . '...' . PHP_EOL;
    } catch (Exception $e) {
        echo 'Note: ' . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . 'Schema import complete.' . PHP_EOL;
