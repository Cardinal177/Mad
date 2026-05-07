<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();

function colExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetch();
}

// --- products.product_type ---
if (colExists($pdo, 'products', 'product_type')) {
    echo "products.product_type already exists\n";
} else {
    $pdo->exec(
        "ALTER TABLE products
         ADD COLUMN product_type ENUM(
             'tørvare','ferskvare','mejeri','kød','fisk',
             'frugt_groent','frostvare','krydderier',
             'drikke','konserves','brød','andet'
         ) NOT NULL DEFAULT 'andet'"
    );
    echo "Added products.product_type\n";
}

// --- household_locations.location_type ---
if (colExists($pdo, 'household_locations', 'location_type')) {
    echo "household_locations.location_type already exists\n";
} else {
    $pdo->exec(
        "ALTER TABLE household_locations
         ADD COLUMN location_type ENUM('dry','fridge','freezer','counter','other') NOT NULL DEFAULT 'dry'"
    );
    echo "Added household_locations.location_type\n";
}

// --- inventory_movements.expires_at ---
if (colExists($pdo, 'inventory_movements', 'expires_at')) {
    echo "inventory_movements.expires_at already exists\n";
} else {
    $pdo->exec(
        "ALTER TABLE inventory_movements
         ADD COLUMN expires_at DATE DEFAULT NULL"
    );
    echo "Added inventory_movements.expires_at\n";
}

echo "Migration complete.\n";
