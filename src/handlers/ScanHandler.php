<?php

declare(strict_types=1);

function handleScan(PDO $pdo): void
{
    $data = parseJsonInput();

    if (!$data || empty($data['barcode'])) {
        response(400, ['error' => 'Missing barcode']);
        return;
    }

    $barcode = trim($data['barcode']);
    $householdId = (int) ($data['household_id'] ?? 1);  // Default to household 1 for now
    $locationId = (int) ($data['location_id'] ?? 1);      // Default to location 1
    $movementType = $data['movement_type'] ?? 'in';        // 'in' or 'out'
    $quantity = (float) ($data['quantity'] ?? 1);

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Find or create product by barcode
        $stmt = $pdo->prepare(
            'SELECT id FROM products WHERE barcode = ? LIMIT 1'
        );
        $stmt->execute([$barcode]);
        $product = $stmt->fetch();

        if (!$product) {
            // Create new product with placeholder data
            $stmt = $pdo->prepare(
                'INSERT INTO products (barcode, name) VALUES (?, ?)'
            );
            $stmt->execute([$barcode, 'Scanned Product ' . substr($barcode, 0, 8)]);
            $productId = (int) $pdo->lastInsertId();
        } else {
            $productId = (int) $product['id'];
        }

        // Record inventory movement
        $quantityDelta = ($movementType === 'out') ? -$quantity : $quantity;

        $stmt = $pdo->prepare(
            'INSERT INTO inventory_movements (household_id, location_id, product_id, movement_type, quantity_delta, source)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $householdId,
            $locationId,
            $productId,
            $movementType,
            $quantityDelta,
            'esp32',
        ]);

        // Update or create inventory record
        $stmt = $pdo->prepare(
            'SELECT id, quantity FROM household_inventory 
             WHERE household_id = ? AND location_id = ? AND product_id = ?
             LIMIT 1'
        );
        $stmt->execute([$householdId, $locationId, $productId]);
        $inventory = $stmt->fetch();

        if ($inventory) {
            $newQuantity = (float) $inventory['quantity'] + $quantityDelta;
            $stmt = $pdo->prepare(
                'UPDATE household_inventory SET quantity = ? WHERE id = ?'
            );
            $stmt->execute([$newQuantity, (int) $inventory['id']]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO household_inventory (household_id, location_id, product_id, quantity)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$householdId, $locationId, $productId, $quantityDelta]);
        }

        $pdo->commit();

        response(201, [
            'status' => 'ok',
            'message' => 'Scan recorded',
            'barcode' => $barcode,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
        ]);

    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleProductList(PDO $pdo): void
{
    $householdId = (int) ($_GET['household_id'] ?? 1);
    $locationId = (int) ($_GET['location_id'] ?? 1);

    try {
        $stmt = $pdo->prepare(
            'SELECT 
                p.id,
                p.barcode,
                p.name,
                p.brand,
                hi.quantity,
                hi.minimum_quantity
             FROM products p
             LEFT JOIN household_inventory hi ON p.id = hi.product_id
                AND hi.household_id = ? AND hi.location_id = ?
             ORDER BY p.name ASC'
        );
        $stmt->execute([$householdId, $locationId]);
        $products = $stmt->fetchAll();

        response(200, [
            'status' => 'ok',
            'products' => $products,
        ]);

    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}
