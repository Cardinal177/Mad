<?php

declare(strict_types=1);

function fetchOpenFoodFactsProduct(string $barcode): ?array
{
    // OFF product API supports numeric EAN/UPC style barcodes best.
    if (!preg_match('/^\d{8,14}$/', $barcode)) {
        return null;
    }

    $enabled = strtolower((string) (env_value('OFF_ENABLED', 'true') ?? 'true')) === 'true';
    if (!$enabled) {
        return null;
    }

    $template = (string) (env_value('OFF_PRODUCT_URL_TEMPLATE', 'https://world.openfoodfacts.org/api/v2/product/%s.json') ?? 'https://world.openfoodfacts.org/api/v2/product/%s.json');
    $userAgent = (string) (env_value('OFF_USER_AGENT', 'Mad/0.3 (ops@example.com)') ?? 'Mad/0.3 (ops@example.com)');
    $timeoutSeconds = (int) (env_value('OFF_TIMEOUT_SECONDS', '5') ?? '5');
    if ($timeoutSeconds < 2 || $timeoutSeconds > 12) {
        $timeoutSeconds = 5;
    }

    $url = sprintf($template, rawurlencode($barcode));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: ' . $userAgent,
        ],
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json) || (int) ($json['status'] ?? 0) !== 1 || !is_array($json['product'] ?? null)) {
        return null;
    }

    $product = $json['product'];
    $name = trim((string) ($product['product_name_da'] ?? $product['product_name'] ?? ''));
    $brand = trim((string) ($product['brands'] ?? ''));
    $imageUrl = trim((string) ($product['image_front_url'] ?? $product['image_url'] ?? ''));

    if ($name === '') {
        return null;
    }

    return [
        'name' => mb_substr($name, 0, 200),
        'brand' => $brand !== '' ? mb_substr($brand, 0, 120) : null,
        'image_url' => $imageUrl !== '' ? mb_substr($imageUrl, 0, 500) : null,
    ];
}

function placeholderProductName(string $barcode): string
{
    return 'Scanned Product ' . substr($barcode, 0, 8);
}

function handleScan(PDO $pdo): void
{
    $expectedDeviceToken = (string) (env_value('DEVICE_TOKEN', '') ?? '');
    $requestDeviceToken = (string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
    if ($expectedDeviceToken !== '' && !hash_equals($expectedDeviceToken, $requestDeviceToken)) {
        response(401, ['error' => 'Unauthorized device token']);
        return;
    }

    $data = parseJsonInput();

    if (!$data || empty($data['barcode'])) {
        response(400, ['error' => 'Missing barcode']);
        return;
    }

    $barcode = trim((string) $data['barcode']);
    $householdId = (int) ($data['household_id'] ?? 1);
    $locationId = (int) ($data['location_id'] ?? 1);
    $movementType = (string) ($data['movement_type'] ?? 'in');
    $quantity = (float) ($data['quantity'] ?? 1);

    if (!in_array($movementType, ['in', 'out', 'adjust'], true)) {
        response(400, ['error' => 'Invalid movement_type']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id FROM households WHERE id = ? LIMIT 1');
        $stmt->execute([$householdId]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO households (id, name) VALUES (?, ?)');
            $stmt->execute([$householdId, 'Default Household']);
        }

        $stmt = $pdo->prepare('SELECT id FROM household_locations WHERE id = ? AND household_id = ? LIMIT 1');
        $stmt->execute([$locationId, $householdId]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO household_locations (id, household_id, name) VALUES (?, ?, ?)');
            $stmt->execute([$locationId, $householdId, 'Default Location']);
        }

        $productLookupSource = 'existing';
        $productNameUsed = null;

        $stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ? LIMIT 1');
        $stmt->execute([$barcode]);
        $product = $stmt->fetch();

        if (!$product) {
            $offProduct = fetchOpenFoodFactsProduct($barcode);
            $name = $offProduct['name'] ?? placeholderProductName($barcode);
            $brand = $offProduct['brand'] ?? null;
            $imageUrl = $offProduct['image_url'] ?? null;

            $productLookupSource = $offProduct ? 'openfoodfacts' : 'placeholder';
            $productNameUsed = $name;

            $stmt = $pdo->prepare('INSERT INTO products (barcode, name, brand, image_url) VALUES (?, ?, ?, ?)');
            $stmt->execute([$barcode, $name, $brand, $imageUrl]);
            $productId = (int) $pdo->lastInsertId();
        } else {
            $productId = (int) $product['id'];
        }

        $quantityDelta = ($movementType === 'out') ? -$quantity : $quantity;

        // Server-side dedupe: ignore same scan repeated within 6 seconds.
        $stmt = $pdo->prepare(
            'SELECT id
             FROM inventory_movements
             WHERE household_id = ?
               AND location_id = ?
               AND product_id = ?
               AND movement_type = ?
               AND source = ?
               AND ABS(quantity_delta - ?) < 0.0001
               AND created_at >= (NOW() - INTERVAL 6 SECOND)
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            $householdId,
            $locationId,
            $productId,
            $movementType,
            'esp32',
            $quantityDelta,
        ]);
        $duplicate = $stmt->fetch();

        if ($duplicate) {
            $pdo->commit();
            response(200, [
                'status' => 'ok',
                'message' => 'Duplicate scan ignored',
                'barcode' => $barcode,
                'product_id' => $productId,
                'product_lookup_source' => $productLookupSource,
                'product_name' => $productNameUsed,
                'movement_type' => $movementType,
                'quantity_delta' => $quantityDelta,
                'duplicate_ignored' => true,
            ]);
            return;
        }

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

        $stmt = $pdo->prepare(
            'SELECT id, quantity
             FROM household_inventory
             WHERE household_id = ? AND location_id = ? AND product_id = ?
             LIMIT 1'
        );
        $stmt->execute([$householdId, $locationId, $productId]);
        $inventory = $stmt->fetch();

        if ($inventory) {
            $newQuantity = (float) $inventory['quantity'] + $quantityDelta;
            $stmt = $pdo->prepare('UPDATE household_inventory SET quantity = ? WHERE id = ?');
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
            'product_lookup_source' => $productLookupSource,
            'product_name' => $productNameUsed,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

function handleRecentScans(PDO $pdo): void
{
    $householdId = (int) ($_GET['household_id'] ?? 1);
    $limit = (int) ($_GET['limit'] ?? 20);
    if ($limit < 1 || $limit > 200) {
        $limit = 20;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT
                im.id,
                im.created_at,
                im.movement_type,
                im.quantity_delta,
                im.location_id,
                p.barcode,
                p.name AS product_name
             FROM inventory_movements im
             INNER JOIN products p ON p.id = im.product_id
             WHERE im.household_id = ?
             ORDER BY im.created_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$householdId]);
        $rows = $stmt->fetchAll();

        response(200, [
            'status' => 'ok',
            'scans' => $rows,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}
