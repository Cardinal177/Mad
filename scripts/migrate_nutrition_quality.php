<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
}

$changes = [
    'nutrition_source' => "ALTER TABLE products ADD COLUMN nutrition_source ENUM('unknown', 'off_label', 'frida_dtu', 'manual', 'placeholder') NOT NULL DEFAULT 'unknown' AFTER nutrition_json",
    'nutrition_confidence' => 'ALTER TABLE products ADD COLUMN nutrition_confidence DECIMAL(4,3) DEFAULT NULL AFTER nutrition_source',
    'frida_food_code' => 'ALTER TABLE products ADD COLUMN frida_food_code VARCHAR(64) DEFAULT NULL AFTER nutrition_confidence',
    'nutrition_updated_at' => 'ALTER TABLE products ADD COLUMN nutrition_updated_at DATETIME DEFAULT NULL AFTER frida_food_code',
];

foreach ($changes as $column => $sql) {
    if (columnExists($pdo, 'products', $column)) {
        echo "products.$column already exists\n";
        continue;
    }

    $pdo->exec($sql);
    echo "Added products.$column\n";
}

$updated = $pdo->exec(
    "UPDATE products
     SET nutrition_source = CASE
            WHEN nutrition_source = 'unknown' AND nutrition_json IS NOT NULL THEN 'off_label'
            WHEN nutrition_source = 'unknown' AND nutrition_json IS NULL THEN 'placeholder'
            ELSE nutrition_source
         END,
         nutrition_confidence = CASE
            WHEN nutrition_confidence IS NULL AND nutrition_json IS NOT NULL THEN 0.500
            ELSE nutrition_confidence
         END,
         nutrition_updated_at = CASE
            WHEN nutrition_updated_at IS NULL AND nutrition_json IS NOT NULL THEN NOW()
            ELSE nutrition_updated_at
         END"
);

echo 'Backfilled existing rows: ' . (int) $updated . "\n";
