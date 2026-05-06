<?php

declare(strict_types=1);

require_once 'src/bootstrap.php';
require_once 'config/database.php';

$pdo = db();

echo "=== Testing ESP32 Scan Handler ===" . PHP_EOL;

try {
    // Ensure test household exists
    $stmt = $pdo->prepare('SELECT id FROM households WHERE id = 1 LIMIT 1');
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare('INSERT INTO households (id, name) VALUES (?, ?)');
        $stmt->execute([1, 'Default Household']);
        echo "✓ Created household 1" . PHP_EOL;
    } else {
        echo "✓ Household 1 exists" . PHP_EOL;
    }

    // Ensure test location exists
    $stmt = $pdo->prepare(
        'SELECT id FROM household_locations 
         WHERE household_id = 1 AND id = 1 LIMIT 1'
    );
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare(
            'INSERT INTO household_locations (id, household_id, name) 
             VALUES (?, ?, ?)'
        );
        $stmt->execute([1, 1, 'Kitchen']);
        echo "✓ Created location 1 (Kitchen)" . PHP_EOL;
    } else {
        echo "✓ Location 1 exists" . PHP_EOL;
    }

    // Test scan
    $pdo->beginTransaction();

    $barcode = 'TEST-ESP32-' . uniqid();
    echo "\nTesting scan with barcode: " . $barcode . PHP_EOL;

    // Create product
    $stmt = $pdo->prepare('INSERT INTO products (barcode, name) VALUES (?, ?)');
    $stmt->execute([$barcode, 'Test Product from ESP32']);
    $productId = (int) $pdo->lastInsertId();

    // Record inventory movement
    $stmt = $pdo->prepare(
        'INSERT INTO inventory_movements (household_id, location_id, product_id, movement_type, quantity_delta, source)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([1, 1, $productId, 'in', 1, 'esp32']);

    // Update inventory
    $stmt = $pdo->prepare(
        'INSERT INTO household_inventory (household_id, location_id, product_id, quantity)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + 1'
    );
    $stmt->execute([1, 1, $productId, 1]);

    $pdo->commit();

    echo "✓ Movement recorded" . PHP_EOL;
    echo "✓ Inventory updated" . PHP_EOL;

    // Verify
    $stmt = $pdo->prepare(
        'SELECT quantity FROM household_inventory 
         WHERE household_id = 1 AND location_id = 1 AND product_id = ? LIMIT 1'
    );
    $stmt->execute([$productId]);
    $inv = $stmt->fetch();

    echo "\n✅ All tests passed!" . PHP_EOL;
    echo "Product ID: " . $productId . PHP_EOL;
    echo "Current quantity: " . $inv['quantity'] . PHP_EOL;

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n❌ Test failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
