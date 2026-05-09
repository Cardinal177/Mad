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
    $nutrition = is_array($product['nutriments'] ?? null) ? $product['nutriments'] : [];

    // Categories as flat list for downstream enrichment (AI classification etc.)
    $categoriesRaw = $product['categories_tags'] ?? [];
    $categories = is_array($categoriesRaw)
        ? array_slice(
            array_values(array_filter(array_map(
                static fn($c) => preg_replace('/^[a-z]{2}:/', '', (string) $c),
                $categoriesRaw
            ), static fn($c) => $c !== '')),
            0, 8
          )
        : [];

    if ($name === '') {
        return null;
    }

    $nutritionProfile = [
        'per' => '100g',
        'energy_kcal' => isset($nutrition['energy-kcal_100g']) ? (float) $nutrition['energy-kcal_100g'] : null,
        'fat_g' => isset($nutrition['fat_100g']) ? (float) $nutrition['fat_100g'] : null,
        'carbohydrates_g' => isset($nutrition['carbohydrates_100g']) ? (float) $nutrition['carbohydrates_100g'] : null,
        'sugars_g' => isset($nutrition['sugars_100g']) ? (float) $nutrition['sugars_100g'] : null,
        'protein_g' => isset($nutrition['proteins_100g']) ? (float) $nutrition['proteins_100g'] : null,
        'salt_g' => isset($nutrition['salt_100g']) ? (float) $nutrition['salt_100g'] : null,
    ];

    return [
        'name' => mb_substr($name, 0, 200),
        'brand' => $brand !== '' ? mb_substr($brand, 0, 120) : null,
        'image_url' => $imageUrl !== '' ? mb_substr($imageUrl, 0, 500) : null,
        'nutrition_json' => $nutritionProfile,
        'categories' => $categories,
    ];
}

function placeholderProductName(string $barcode): string
{
    return 'Scanned Product ' . substr($barcode, 0, 8);
}

function stripBrandFromProductName(string $productName, string $brand): string
{
    if (!$brand || !$productName) {
        return $productName;
    }

    // Normalize text: lowercase, expand camelCase, remove special chars, split to words
    $normalize = static function (string $s): array {
        $s = preg_replace('/([a-z])([A-Z])/u', '$1 $2', $s);
        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        $words = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
        return $words ?: [];
    };

    $brandWords = $normalize($brand);
    $nameWords = preg_split('/\s+/u', $productName, -1, PREG_SPLIT_NO_EMPTY);

    if (!$brandWords || !$nameWords) {
        return $productName;
    }

    // Try to consume leading nameWords that match brand words
    $consumed = 0;
    $bi = 0;
    for ($ni = 0; $ni < count($nameWords) && $bi < count($brandWords); $ni++) {
        $nw = $normalize($nameWords[$ni]);
        if (count($nw) === 1 && $nw[0] === $brandWords[$bi]) {
            // Single word match
            $consumed = $ni + 1;
            $bi++;
        } elseif (implode('', $nw) === implode('', array_slice($brandWords, $bi, count($nw)))) {
            // Multi-word match (camelCase word matches multiple brand words)
            $consumed = $ni + 1;
            $bi += count($nw);
        } else {
            break;
        }
    }

    if ($consumed > 0) {
        $remaining = implode(' ', array_slice($nameWords, $consumed));
        if (strlen($remaining) > 2) {
            return trim($remaining);
        }
    }

    return $productName;
}

function scanProductsHasColumn(PDO $pdo, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

        $stmt = $pdo->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = ?
                     AND COLUMN_NAME = ?
                 LIMIT 1'
        );
        $stmt->execute(['products', $column]);
        $cache[$column] = (bool) $stmt->fetchColumn();

    return $cache[$column];
}

function scanLocationsHasColumn(PDO $pdo, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

        $stmt = $pdo->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = ?
                     AND COLUMN_NAME = ?
                 LIMIT 1'
        );
        $stmt->execute(['household_locations', $column]);
        $cache[$column] = (bool) $stmt->fetchColumn();

    return $cache[$column];
}

function shoppingListItemsHasColumn(PDO $pdo, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute(['shopping_list_items', $column]);
    $cache[$column] = (bool) $stmt->fetchColumn();

    return $cache[$column];
}

function normalizeBarcode(string $rawBarcode): string
{
    $barcode = trim($rawBarcode);
    if ($barcode === '') {
        return '';
    }

    // Collapse scanner glitches like 5741000124024 repeated multiple times in one payload.
    if (preg_match('/^(\d{8,14})\1+$/', $barcode, $matches) === 1) {
        return $matches[1];
    }

    return $barcode;
}

function persistLastDeviceScan(
    string $barcode,
    string $movementType,
    int $householdId,
    int $locationId,
    bool $duplicateIgnored = false,
    ?int $productId = null,
    ?float $quantityAfter = null
): void
{
    $scanFile = sys_get_temp_dir() . '/mad_last_device_scan.txt';
    $content = json_encode([
        'barcode' => $barcode,
        'movement_type' => $movementType,
        'household_id' => $householdId,
        'location_id' => $locationId,
        'duplicate_ignored' => $duplicateIgnored,
        'product_id' => $productId,
        'quantity_after' => $quantityAfter,
        'timestamp' => time(),
        'set_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES);

    if ($content === false) {
        return;
    }

    @file_put_contents($scanFile, $content);
}

function getOrCreateOpenShoppingListId(PDO $pdo, int $householdId): int
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM shopping_lists
         WHERE household_id = ? AND status = "open"
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmt->execute([$householdId]);
    $existing = $stmt->fetch();
    if ($existing) {
        return (int) $existing['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO shopping_lists (household_id, title, status, created_by_user_id)
         VALUES (?, ?, ?, NULL)'
    );
    $stmt->execute([
        $householdId,
        'Indkøbsseddel ' . date('d. M Y'),
        'open',
    ]);

    return (int) $pdo->lastInsertId();
}

function addProductToShoppingListIfMissing(PDO $pdo, int $householdId, int $productId, string $productName): bool
{
    if ($productId <= 0 || trim($productName) === '') {
        return false;
    }

    $shoppingListId = getOrCreateOpenShoppingListId($pdo, $householdId);

    $stmt = $pdo->prepare(
        'SELECT id
         FROM shopping_list_items
         WHERE shopping_list_id = ? AND product_id = ?
         LIMIT 1'
    );
    $stmt->execute([$shoppingListId, $productId]);
    if ($stmt->fetch()) {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO shopping_list_items (shopping_list_id, product_id, product_name, quantity, preferred_store)
         VALUES (?, ?, ?, ?, NULL)'
    );
    $stmt->execute([$shoppingListId, $productId, trim($productName), 1]);

    return true;
}

function removeProductFromOpenShoppingListIfPresent(PDO $pdo, int $householdId, int $productId): bool
{
    if ($productId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        'DELETE si
         FROM shopping_list_items si
         INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
         WHERE sl.household_id = ?
           AND sl.status = "open"
           AND si.product_id = ?'
    );
    $stmt->execute([$householdId, $productId]);

    return $stmt->rowCount() > 0;
}

function getDeviceScanContext(): array
{
    $contextFile = sys_get_temp_dir() . '/mad_device_scan_context.txt';
    $data = null;

    if (file_exists($contextFile)) {
        $contextContent = file_get_contents($contextFile);
        $contextData = json_decode((string) $contextContent, true);
        if (is_array($contextData)) {
            $data = $contextData;
        }
    }

    // Backward-compatible fallback.
    if (!is_array($data)) {
        $modeFile = sys_get_temp_dir() . '/mad_device_mode.txt';
        if (!file_exists($modeFile)) {
            return [
                'household_id' => null,
                'location_id' => null,
            ];
        }

        $content = file_get_contents($modeFile);
        $decoded = json_decode((string) $content, true);
        if (!is_array($decoded)) {
            return [
                'household_id' => null,
                'location_id' => null,
            ];
        }
        $data = $decoded;
    }

    $householdId = isset($data['household_id']) ? (int) $data['household_id'] : null;
    $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;

    return [
        'household_id' => ($householdId !== null && $householdId > 0) ? $householdId : null,
        'location_id' => ($locationId !== null && $locationId > 0) ? $locationId : null,
    ];
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

    $rawBarcode = (string) $data['barcode'];
    $barcode = normalizeBarcode($rawBarcode);
    if ($barcode === '') {
        response(400, ['error' => 'Missing barcode']);
        return;
    }
    $requestedHouseholdId = (int) ($data['household_id'] ?? 1);
    $requestedLocationId = (int) ($data['location_id'] ?? 1);
    $deviceContext = getDeviceScanContext();
    $householdId = (int) ($deviceContext['household_id'] ?? $requestedHouseholdId ?: 1);
    $locationId = (int) ($deviceContext['location_id'] ?? $requestedLocationId ?: 1);
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
            response(404, ['error' => 'Household not found']);
            return;
        }

        $stmt = $pdo->prepare('SELECT id FROM household_locations WHERE id = ? AND household_id = ? LIMIT 1');
        $stmt->execute([$locationId, $householdId]);
        if (!$stmt->fetch()) {
            // Fallback to first valid location for the household instead of failing the scan.
            $fallbackStmt = $pdo->prepare(
                'SELECT id
                 FROM household_locations
                 WHERE household_id = ?
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $fallbackStmt->execute([$householdId]);
            $fallback = $fallbackStmt->fetch();
            if (!$fallback) {
                response(404, ['error' => 'Location not found for household']);
                return;
            }
            $locationId = (int) $fallback['id'];
        }

        $productLookupSource = 'existing';
        $productNameUsed = null;

        $stmt = $pdo->prepare('SELECT id, name, brand, image_url, nutrition_json FROM products WHERE barcode = ? LIMIT 1');
        $stmt->execute([$barcode]);
        $product = $stmt->fetch();
        $resolvedProductName = '';

        if (!$product) {
            $offProduct = fetchOpenFoodFactsProduct($barcode);
            $name = $offProduct['name'] ?? placeholderProductName($barcode);
            $brand = $offProduct['brand'] ?? null;
            $imageUrl = $offProduct['image_url'] ?? null;
            $nutritionJson = isset($offProduct['nutrition_json']) ? json_encode($offProduct['nutrition_json'], JSON_UNESCAPED_SLASHES) : null;

            $productLookupSource = $offProduct ? 'openfoodfacts' : 'placeholder';
            $productNameUsed = $name;

            $hasSource = scanProductsHasColumn($pdo, 'nutrition_source');
            $hasConfidence = scanProductsHasColumn($pdo, 'nutrition_confidence');
            $hasUpdatedAt = scanProductsHasColumn($pdo, 'nutrition_updated_at');

            if ($hasSource && $hasConfidence && $hasUpdatedAt) {
                $source = $offProduct && $nutritionJson !== null ? 'off_label' : 'placeholder';
                $confidence = $offProduct && $nutritionJson !== null ? 0.500 : null;
                $nutritionUpdatedAt = $offProduct && $nutritionJson !== null ? date('Y-m-d H:i:s') : null;

                $stmt = $pdo->prepare(
                    'INSERT INTO products
                     (barcode, name, brand, image_url, nutrition_json, nutrition_source, nutrition_confidence, nutrition_updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$barcode, $name, $brand, $imageUrl, $nutritionJson, $source, $confidence, $nutritionUpdatedAt]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO products (barcode, name, brand, image_url, nutrition_json) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$barcode, $name, $brand, $imageUrl, $nutritionJson]);
            }
            $productId = (int) $pdo->lastInsertId();
            $resolvedProductName = $name;
        } else {
            $productId = (int) $product['id'];
            $resolvedProductName = (string) ($product['name'] ?? '');

            $currentName = (string) ($product['name'] ?? '');
            $needsRefresh = str_starts_with($currentName, 'Scanned Product ')
                || (($product['brand'] ?? null) === null)
                || (($product['image_url'] ?? null) === null)
                || (($product['nutrition_json'] ?? null) === null);

            if ($needsRefresh) {
                $offProduct = fetchOpenFoodFactsProduct($barcode);
                if ($offProduct) {
                    $hasSource = scanProductsHasColumn($pdo, 'nutrition_source');
                    $hasConfidence = scanProductsHasColumn($pdo, 'nutrition_confidence');
                    $hasUpdatedAt = scanProductsHasColumn($pdo, 'nutrition_updated_at');

                    if ($hasSource && $hasConfidence && $hasUpdatedAt) {
                        $stmt = $pdo->prepare(
                            'UPDATE products
                             SET name = ?,
                                 brand = ?,
                                 image_url = ?,
                                 nutrition_json = ?,
                                 nutrition_source = ?,
                                 nutrition_confidence = ?,
                                 nutrition_updated_at = ?
                             WHERE id = ?'
                        );
                        $stmt->execute([
                            $offProduct['name'],
                            $offProduct['brand'] ?? null,
                            $offProduct['image_url'] ?? null,
                            json_encode($offProduct['nutrition_json'] ?? null, JSON_UNESCAPED_SLASHES),
                            'off_label',
                            0.500,
                            date('Y-m-d H:i:s'),
                            $productId,
                        ]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE products SET name = ?, brand = ?, image_url = ?, nutrition_json = ? WHERE id = ?');
                        $stmt->execute([
                            $offProduct['name'],
                            $offProduct['brand'] ?? null,
                            $offProduct['image_url'] ?? null,
                            json_encode($offProduct['nutrition_json'] ?? null, JSON_UNESCAPED_SLASHES),
                            $productId,
                        ]);
                    }
                    $productLookupSource = 'openfoodfacts-refresh';
                    $productNameUsed = $offProduct['name'];
                    $resolvedProductName = (string) ($offProduct['name'] ?? $resolvedProductName);
                }
            }
        }

        if ($resolvedProductName === '') {
            $resolvedProductName = (string) ($productNameUsed ?? 'Vare');
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
            persistLastDeviceScan($barcode, $movementType, $householdId, $locationId, true, $productId, null);
            response(200, [
                'status' => 'ok',
                'message' => 'Duplicate scan ignored',
                'barcode' => $barcode,
                'barcode_raw' => $rawBarcode,
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
            'SELECT id, quantity, minimum_quantity
             FROM household_inventory
             WHERE household_id = ? AND location_id = ? AND product_id = ?
             LIMIT 1'
        );
        $stmt->execute([$householdId, $locationId, $productId]);
        $inventory = $stmt->fetch();
        $autoAddedToShoppingList = false;
        $autoRemovedFromShoppingList = false;
        $preventedNegative = false;

        $quantityAfter = null;
        if ($inventory) {
            $currentQuantity = (float) ($inventory['quantity'] ?? 0);
            $newQuantity = $currentQuantity + $quantityDelta;
            if ($newQuantity < 0) {
                $newQuantity = 0.0;
                $preventedNegative = true;
            }
            $stmt = $pdo->prepare('UPDATE household_inventory SET quantity = ? WHERE id = ?');
            $stmt->execute([$newQuantity, (int) $inventory['id']]);
            $quantityAfter = $newQuantity;

            $minimumQuantity = (float) ($inventory['minimum_quantity'] ?? 0);
            if ($movementType === 'out' && $minimumQuantity > 0 && $newQuantity <= ($minimumQuantity + 0.0001)) {
                $autoAddedToShoppingList = addProductToShoppingListIfMissing($pdo, $householdId, $productId, $resolvedProductName);
            } elseif ($movementType === 'in' && $minimumQuantity > 0 && $newQuantity >= ($minimumQuantity - 0.0001)) {
                $autoRemovedFromShoppingList = removeProductFromOpenShoppingListIfPresent($pdo, $householdId, $productId);
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO household_inventory (household_id, location_id, product_id, quantity)
                 VALUES (?, ?, ?, ?)'
            );
            $initialQuantity = $quantityDelta < 0 ? 0.0 : $quantityDelta;
            if ($quantityDelta < 0) {
                $preventedNegative = true;
            }
            $stmt->execute([$householdId, $locationId, $productId, $initialQuantity]);
            $quantityAfter = $initialQuantity;
        }

        $pdo->commit();
        persistLastDeviceScan($barcode, $movementType, $householdId, $locationId, false, $productId, $quantityAfter);

        response(201, [
            'status' => 'ok',
            'message' => 'Scan recorded',
            'barcode' => $barcode,
            'barcode_raw' => $rawBarcode,
            'product_id' => $productId,
            'product_lookup_source' => $productLookupSource,
            'product_name' => $productNameUsed,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'auto_added_to_shopping_list' => $autoAddedToShoppingList,
            'auto_removed_from_shopping_list' => $autoRemovedFromShoppingList,
            'prevented_negative' => $preventedNegative,
            'household_id' => $householdId,
            'location_id' => $locationId,
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
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);
    $locationId = isset($_GET['location_id']) ? (int) $_GET['location_id'] : null;

    try {
        $productTypeSelect = scanProductsHasColumn($pdo, 'product_type')
            ? 'p.product_type'
            : 'NULL AS product_type';
        $productWeightSelect = scanProductsHasColumn($pdo, 'weight_grams')
            ? 'p.weight_grams'
            : 'NULL AS weight_grams';
        $locationTypeSelect = scanLocationsHasColumn($pdo, 'location_type')
            ? 'hl.location_type'
            : 'NULL AS location_type';

        if ($locationId !== null && $locationId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM household_locations WHERE id = ? AND household_id = ? LIMIT 1');
            $stmt->execute([$locationId, $householdId]);
            if (!$stmt->fetch()) {
                response(403, ['error' => 'Forbidden location']);
                return;
            }
        }

        $extraFields = '';
        if (scanProductsHasColumn($pdo, 'nutrition_source')) {
            $extraFields .= ', p.nutrition_source';
        }
        if (scanProductsHasColumn($pdo, 'nutrition_confidence')) {
            $extraFields .= ', p.nutrition_confidence';
        }
        if (scanProductsHasColumn($pdo, 'frida_food_code')) {
            $extraFields .= ', p.frida_food_code';
        }
        if (scanProductsHasColumn($pdo, 'nutrition_updated_at')) {
            $extraFields .= ', p.nutrition_updated_at';
        }

        $stmt = $pdo->prepare(
            'SELECT
                p.id,
                p.barcode,
                p.name,
                p.brand,
                p.image_url,
                p.nutrition_json,
                ' . $productTypeSelect . ',
                ' . $productWeightSelect . ',
                hi.quantity,
                hi.minimum_quantity,
                hi.location_id,
                hl.name AS location_name,
                ' . $locationTypeSelect . ',
                standard_offer.store_name AS standard_store,
                standard_offer.price AS standard_price,
                promo_offer.store_name AS offer_store,
                promo_offer.price AS offer_price,
                promo_offer.valid_to AS offer_valid_to' . $extraFields . '
             FROM household_inventory hi
             INNER JOIN products p ON p.id = hi.product_id
             LEFT JOIN household_locations hl ON hl.id = hi.location_id
             LEFT JOIN (
                 SELECT so.product_id, so.store_name, so.price, so.valid_to
                 FROM store_offers so
                 INNER JOIN (
                     SELECT product_id, MAX(id) AS max_id
                     FROM store_offers
                     WHERE title = "Standardpris"
                     GROUP BY product_id
                 ) latest ON latest.max_id = so.id
             ) standard_offer ON standard_offer.product_id = p.id
             LEFT JOIN (
                 SELECT so.product_id, so.store_name, so.price, so.valid_to
                 FROM store_offers so
                 INNER JOIN (
                     SELECT product_id, MAX(id) AS max_id
                     FROM store_offers
                     WHERE title = "Tilbud"
                     GROUP BY product_id
                 ) latest ON latest.max_id = so.id
             ) promo_offer ON promo_offer.product_id = p.id
             WHERE hi.household_id = ?
               AND (? IS NULL OR hi.location_id = ?)
             ORDER BY p.name ASC'
        );
        $stmt->execute([$householdId, $locationId, $locationId]);
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
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);
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

function handleLocationList(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    try {
        $locationTypeSelect = scanLocationsHasColumn($pdo, 'location_type')
            ? 'location_type'
            : "'other' AS location_type";

        $stmt = $pdo->prepare(
            'SELECT id, household_id, name, ' . $locationTypeSelect . ', created_at
             FROM household_locations
             WHERE household_id = ?
             ORDER BY name ASC, id ASC'
        );
        $stmt->execute([$householdId]);

        response(200, [
            'status' => 'ok',
            'locations' => $stmt->fetchAll() ?: [],
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleShoppingCandidates(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);
    $limit = (int) ($_GET['limit'] ?? 50);
    if ($limit < 1 || $limit > 200) {
        $limit = 50;
    }

    try {
        $items = [];

        $stmt = $pdo->prepare(
            'SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.brand,
                p.product_type,
                SUM(hi.quantity) AS quantity,
                SUM(hi.minimum_quantity) AS minimum_quantity
             FROM household_inventory hi
             INNER JOIN products p ON p.id = hi.product_id
             WHERE hi.household_id = ?
             GROUP BY p.id, p.name, p.brand, p.product_type
             HAVING SUM(hi.quantity) <= SUM(hi.minimum_quantity)
             ORDER BY (SUM(hi.minimum_quantity) - SUM(hi.quantity)) DESC, p.name ASC
             LIMIT ' . $limit
        );
        $stmt->execute([$householdId]);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['quantity'] ?? 0);
            $min = (float) ($row['minimum_quantity'] ?? 0);
            $needed = max($min - $qty, 0.0);

            $items['p:' . $productId] = [
                'product_id' => $productId,
                'product_name' => (string) ($row['product_name'] ?? 'Ukendt vare'),
                'brand' => (string) ($row['brand'] ?? ''),
                'product_type' => (string) ($row['product_type'] ?? 'andet'),
                'current_quantity' => $qty,
                'minimum_quantity' => $min,
                'needed_quantity' => $needed,
                'trigger_reason' => 'minimum nået',
                'source' => 'auto_low_stock',
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT
                si.id,
                si.product_id,
                si.product_name,
                si.quantity,
                p.name AS linked_product_name,
                p.brand,
                p.product_type
             FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             LEFT JOIN products p ON p.id = si.product_id
             WHERE sl.household_id = ?
               AND sl.status IN ("open", "in_progress")
               AND si.is_checked = 0
             ORDER BY si.created_at DESC, si.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$householdId]);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $name = trim((string) ($row['linked_product_name'] ?? $row['product_name'] ?? ''));
            if ($name === '') {
                $name = 'Ukendt vare';
            }
            $key = $productId > 0 ? 'p:' . $productId : 'n:' . mb_strtolower($name);

            if (isset($items[$key])) {
                $items[$key]['trigger_reason'] = 'minimum nået + manuelt tilføjet';
                $items[$key]['source'] = 'auto_and_manual';
                $items[$key]['needed_quantity'] = max(
                    (float) ($items[$key]['needed_quantity'] ?? 0),
                    (float) ($row['quantity'] ?? 1)
                );
                continue;
            }

            $items[$key] = [
                'product_id' => $productId > 0 ? $productId : null,
                'product_name' => $name,
                'brand' => (string) ($row['brand'] ?? ''),
                'product_type' => (string) ($row['product_type'] ?? 'andet'),
                'current_quantity' => null,
                'minimum_quantity' => null,
                'needed_quantity' => max((float) ($row['quantity'] ?? 1), 1.0),
                'trigger_reason' => 'manuelt tilføjet',
                'source' => 'manual_list',
            ];
        }

        $productIds = [];
        foreach ($items as $item) {
            if (isset($item['product_id']) && (int) $item['product_id'] > 0) {
                $productIds[] = (int) $item['product_id'];
            }
        }
        $productIds = array_values(array_unique($productIds));

        $bestOffersByProduct = [];
        if ($productIds !== []) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare(
                'SELECT so.product_id, so.store_name, so.price, so.valid_to, so.id
                 FROM store_offers so
                 INNER JOIN (
                     SELECT product_id, MIN(price) AS min_price
                     FROM store_offers
                     WHERE title = "Tilbud"
                       AND product_id IN (' . $placeholders . ')
                       AND (valid_to IS NULL OR valid_to >= CURDATE())
                     GROUP BY product_id
                 ) best ON best.product_id = so.product_id AND best.min_price = so.price
                 WHERE so.title = "Tilbud"
                   AND (so.valid_to IS NULL OR so.valid_to >= CURDATE())
                 ORDER BY so.product_id ASC, so.valid_to ASC, so.id DESC'
            );
            $stmt->execute($productIds);

            foreach ($stmt->fetchAll() ?: [] as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId <= 0 || isset($bestOffersByProduct[$productId])) {
                    continue;
                }
                $bestOffersByProduct[$productId] = [
                    'offer_store' => (string) ($row['store_name'] ?? ''),
                    'offer_price' => isset($row['price']) ? (float) $row['price'] : null,
                    'offer_valid_to' => $row['valid_to'] !== null ? (string) $row['valid_to'] : null,
                ];
            }
        }

        $resultItems = [];
        foreach ($items as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $offer = $productId > 0 && isset($bestOffersByProduct[$productId])
                ? $bestOffersByProduct[$productId]
                : ['offer_store' => null, 'offer_price' => null, 'offer_valid_to' => null];

            $resultItems[] = [
                'product_id' => $productId > 0 ? $productId : null,
                'product_name' => (string) ($item['product_name'] ?? 'Ukendt vare'),
                'brand' => (string) ($item['brand'] ?? ''),
                'product_type' => (string) ($item['product_type'] ?? 'andet'),
                'current_quantity' => $item['current_quantity'],
                'minimum_quantity' => $item['minimum_quantity'],
                'needed_quantity' => (float) ($item['needed_quantity'] ?? 1),
                'trigger_reason' => (string) ($item['trigger_reason'] ?? ''),
                'source' => (string) ($item['source'] ?? ''),
                'offer_store' => $offer['offer_store'],
                'offer_price' => $offer['offer_price'],
                'offer_valid_to' => $offer['offer_valid_to'],
                'has_offer' => $offer['offer_price'] !== null,
            ];
        }

        usort($resultItems, static function (array $a, array $b): int {
            $aHasOffer = (int) (!empty($a['has_offer']));
            $bHasOffer = (int) (!empty($b['has_offer']));
            if ($aHasOffer !== $bHasOffer) {
                return $bHasOffer <=> $aHasOffer;
            }

            $aNeed = (float) ($a['needed_quantity'] ?? 0);
            $bNeed = (float) ($b['needed_quantity'] ?? 0);
            if ($aNeed !== $bNeed) {
                return $bNeed <=> $aNeed;
            }

            return strcmp((string) ($a['product_name'] ?? ''), (string) ($b['product_name'] ?? ''));
        });

        response(200, [
            'status' => 'ok',
            'household_id' => $householdId,
            'items' => $resultItems,
            'summary' => [
                'total_candidates' => count($resultItems),
                'with_offer' => count(array_filter($resultItems, static fn(array $item): bool => !empty($item['has_offer']))),
            ],
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleShoppingOfferFeed(PDO $pdo): void
{
    requireAuthenticatedSession($pdo);

    $limit = (int) ($_GET['limit'] ?? 120);
    if ($limit < 1 || $limit > 500) {
        $limit = 120;
    }

    $storeFilter = trim((string) ($_GET['store'] ?? ''));

    try {
        $query = 'SELECT
                so.id,
                so.store_name,
                so.product_id,
                so.title,
                so.price,
                so.valid_from,
                so.valid_to,
                so.source_url,
                so.created_at,
                p.name AS linked_product_name
             FROM store_offers so
             LEFT JOIN products p ON p.id = so.product_id
             WHERE so.title LIKE "Tilbud:%"';

        $params = [];

        if ($storeFilter !== '') {
            $query .= ' AND so.store_name = ?';
            $params[] = $storeFilter;
        }

        $query .= ' ORDER BY so.created_at DESC LIMIT ' . $limit;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $items = array_map(static function (array $row): array {
            $title = (string) ($row['title'] ?? '');
            $leafletName = trim((string) preg_replace('/^Tilbud:\s*/u', '', $title));
            $resolvedName = $leafletName !== '' ? $leafletName : ($row['linked_product_name'] ?? 'Ukendt vare');

            return [
                'id' => (int) ($row['id'] ?? 0),
                'store_name' => (string) ($row['store_name'] ?? ''),
                'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : null,
                'product_name' => (string) $resolvedName,
                'title' => $title,
                'price' => isset($row['price']) ? (float) $row['price'] : null,
                'valid_from' => $row['valid_from'] !== null ? (string) $row['valid_from'] : null,
                'valid_to' => $row['valid_to'] !== null ? (string) $row['valid_to'] : null,
                'source_url' => $row['source_url'] !== null ? (string) $row['source_url'] : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'is_catalog_matched' => !empty($row['product_id']),
            ];
        }, $stmt->fetchAll() ?: []);

        response(200, [
            'status' => 'ok',
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'matched' => count(array_filter($items, static fn(array $item): bool => !empty($item['is_catalog_matched']))),
                'unmatched' => count(array_filter($items, static fn(array $item): bool => empty($item['is_catalog_matched']))),
            ],
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleShoppingList(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    try {
        $stmt = $pdo->prepare(
            'SELECT id, title, status, created_at, updated_at
             FROM shopping_lists
             WHERE household_id = ?
               AND status IN ("open", "in_progress")
             ORDER BY CASE WHEN status = "open" THEN 0 ELSE 1 END, created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$householdId]);
        $shoppingList = $stmt->fetch();

        if (!$shoppingList) {
            response(200, [
                'status' => 'ok',
                'household_id' => $householdId,
                'list' => null,
                'items' => [],
                'summary' => [
                    'total_items' => 0,
                    'checked_items' => 0,
                    'unchecked_items' => 0,
                ],
            ]);
            return;
        }

        $shoppingListId = (int) ($shoppingList['id'] ?? 0);
        $hasOfferIdColumn = shoppingListItemsHasColumn($pdo, 'offer_id');
        $offerIdSelect = $hasOfferIdColumn ? 'si.offer_id' : 'NULL AS offer_id';
        $fallbackOfferIdSelect = $hasOfferIdColumn
            ? '(
                    SELECT so.id
                    FROM store_offers so
                    WHERE LOWER(TRIM(so.title)) = LOWER(TRIM(si.product_name))
                      AND (
                          si.preferred_store IS NULL
                          OR TRIM(si.preferred_store) = ""
                          OR LOWER(TRIM(so.store_name)) = LOWER(TRIM(si.preferred_store))
                      )
                    ORDER BY so.created_at DESC, so.id DESC
                    LIMIT 1
                ) AS fallback_offer_id'
            : 'NULL AS fallback_offer_id';

        $stmt = $pdo->prepare(
            'SELECT
                si.id,
                si.product_id,
                si.product_name,
                si.quantity,
                si.preferred_store,
                si.is_checked,
                ' . $offerIdSelect . ',
                si.offer_price,
                si.offer_valid_until,
                si.created_at,
                p.name AS linked_product_name,
                p.brand,
                p.product_type,
                (
                    SELECT so.price
                    FROM store_offers so
                    WHERE LOWER(TRIM(so.title)) = LOWER(TRIM(si.product_name))
                      AND (
                          si.preferred_store IS NULL
                          OR TRIM(si.preferred_store) = ""
                          OR LOWER(TRIM(so.store_name)) = LOWER(TRIM(si.preferred_store))
                      )
                    ORDER BY so.created_at DESC, so.id DESC
                    LIMIT 1
                ) AS fallback_offer_price,
                (
                    SELECT so.valid_to
                    FROM store_offers so
                    WHERE LOWER(TRIM(so.title)) = LOWER(TRIM(si.product_name))
                      AND (
                          si.preferred_store IS NULL
                          OR TRIM(si.preferred_store) = ""
                          OR LOWER(TRIM(so.store_name)) = LOWER(TRIM(si.preferred_store))
                      )
                    ORDER BY so.created_at DESC, so.id DESC
                    LIMIT 1
                ) AS fallback_offer_valid_to,
                ' . $fallbackOfferIdSelect . '
             FROM shopping_list_items si
             LEFT JOIN products p ON p.id = si.product_id
             WHERE si.shopping_list_id = ?
             ORDER BY si.is_checked ASC, si.created_at DESC, si.id DESC'
        );
        $stmt->execute([$shoppingListId]);

        $items = array_map(static function (array $row): array {
            $name = trim((string) ($row['linked_product_name'] ?? $row['product_name'] ?? ''));
            if ($name === '') {
                $name = 'Ukendt vare';
            }

            $brand = (string) ($row['brand'] ?? '');
            if ($brand && $name !== 'Ukendt vare') {
                $name = stripBrandFromProductName($name, $brand);
            }

            $storedOfferId = isset($row['offer_id']) && $row['offer_id'] !== null ? (int) $row['offer_id'] : null;
            $storedOfferPrice = $row['offer_price'] !== null ? (float) $row['offer_price'] : null;
            $exactFallbackPrice = $row['fallback_offer_price'] !== null ? (float) $row['fallback_offer_price'] : null;

            $offerSource = 'none';
            if ($storedOfferId !== null && $storedOfferId > 0) {
                $offerSource = 'stored_offer_id';
            } elseif ($storedOfferPrice !== null) {
                $offerSource = 'stored_offer_price';
            } elseif ($exactFallbackPrice !== null) {
                $offerSource = 'exact_title_fallback';
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : null,
                'product_name' => $name,
                'brand' => (string) ($row['brand'] ?? ''),
                'product_type' => (string) ($row['product_type'] ?? 'andet'),
                'quantity' => max((float) ($row['quantity'] ?? 1), 1.0),
                'preferred_store' => (string) ($row['preferred_store'] ?? ''),
                'offer_id' => $storedOfferId,
                'offer_price' => ($storedOfferPrice !== null)
                    ? $storedOfferPrice
                    : $exactFallbackPrice,
                'offer_valid_to' => ($row['offer_valid_until'] !== null)
                    ? (string) $row['offer_valid_until']
                    : (($row['fallback_offer_valid_to'] !== null) ? (string) $row['fallback_offer_valid_to'] : null),
                'offer_store' => '',
                'offer_source' => $offerSource,
                'offer_match_score' => null,
                'is_checked' => !empty($row['is_checked']),
                'created_at' => $row['created_at'] !== null ? (string) $row['created_at'] : null,
            ];
        }, $stmt->fetchAll() ?: []);

        foreach ($items as $index => $item) {
            $hasPrice = isset($item['offer_price']) && $item['offer_price'] !== null;
            if ($hasPrice) {
                continue;
            }

            $fallback = resolveBestOfferForShoppingItem(
                $pdo,
                (string) ($item['product_name'] ?? ''),
                (string) ($item['preferred_store'] ?? '')
            );
            if ($fallback === null) {
                continue;
            }

            $items[$index]['offer_id'] = isset($fallback['offer_id']) && $fallback['offer_id'] !== null
                ? (int) $fallback['offer_id']
                : ($items[$index]['offer_id'] ?? null);
            $items[$index]['offer_price'] = $fallback['offer_price'];
            $items[$index]['offer_valid_to'] = $fallback['offer_valid_to'];
            $items[$index]['offer_store'] = $fallback['offer_store'];
            $items[$index]['offer_source'] = (string) ($fallback['offer_source'] ?? 'fuzzy_fallback');
            $items[$index]['offer_match_score'] = isset($fallback['score']) ? (float) $fallback['score'] : null;
        }

        $checkedItems = count(array_filter($items, static fn(array $item): bool => !empty($item['is_checked'])));

        response(200, [
            'status' => 'ok',
            'household_id' => $householdId,
            'list' => [
                'id' => $shoppingListId,
                'title' => (string) ($shoppingList['title'] ?? 'Indkøbsseddel'),
                'status' => (string) ($shoppingList['status'] ?? 'open'),
                'created_at' => $shoppingList['created_at'] !== null ? (string) $shoppingList['created_at'] : null,
                'updated_at' => $shoppingList['updated_at'] !== null ? (string) ($shoppingList['updated_at'] ?? '') : null,
            ],
            'items' => $items,
            'summary' => [
                'total_items' => count($items),
                'checked_items' => $checkedItems,
                'unchecked_items' => max(count($items) - $checkedItems, 0),
            ],
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function normalizeShoppingMatchText(string $value): string
{
    $text = mb_strtolower(trim($value), 'UTF-8');
    $text = strtr($text, [
        'æ' => 'ae',
        'ø' => 'oe',
        'å' => 'aa',
    ]);
    $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

function shoppingMatchTokens(string $value): array
{
    $normalized = normalizeShoppingMatchText($value);
    if ($normalized === '') {
        return [];
    }

    $parts = preg_split('/\s+/u', $normalized) ?: [];
    $tokens = [];
    foreach ($parts as $part) {
        $token = trim($part);
        if ($token === '' || mb_strlen($token, 'UTF-8') < 3) {
            continue;
        }
        $tokens[$token] = true;
    }
    return array_map(static fn($token): string => (string) $token, array_keys($tokens));
}

function isShoppingStopToken(string $token): bool
{
    static $stop = [
        'tilbud' => true,
        'udvalgte' => true,
        'oekologisk' => true,
        'okologisk' => true,
        'med' => true,
        'for' => true,
        'og' => true,
        'eller' => true,
        'stk' => true,
        'ml' => true,
        'cl' => true,
        'gr' => true,
        'g' => true,
        'kg' => true,
        'l' => true,
        'x' => true,
    ];
    return isset($stop[$token]);
}

function hasStrongTokenOverlap(string $shoppingTitle, string $offerTitle): bool
{
    $aTokens = shoppingMatchTokens($shoppingTitle);
    $bTokens = shoppingMatchTokens($offerTitle);
    if ($aTokens === [] || $bTokens === []) {
        return false;
    }

    $bSet = array_fill_keys($bTokens, true);
    foreach ($aTokens as $token) {
        if (mb_strlen($token, 'UTF-8') < 5) {
            continue;
        }
        if (isShoppingStopToken($token)) {
            continue;
        }
        if (isset($bSet[$token])) {
            return true;
        }
    }

    return false;
}

function knownFoodKeywords(string $value): array
{
    static $known = [
        'skyr' => true,
        'yoghurt' => true,
        'maelk' => true,
        'ost' => true,
        'smoer' => true,
        'floe' => true,
        'flode' => true,
        'marmelade' => true,
        'kakao' => true,
        'juice' => true,
        'cola' => true,
        'oel' => true,
        'broed' => true,
        'rugbroed' => true,
        'paalaeg' => true,
        'palaeg' => true,
        'rullepoelse' => true,
        'rullepolse' => true,
        'kylling' => true,
        'okse' => true,
        'svin' => true,
        'fars' => true,
        'fisk' => true,
        'laks' => true,
        'tun' => true,
        'pasta' => true,
        'ris' => true,
        'tomat' => true,
        'kartoffel' => true,
        'loeg' => true,
        'banan' => true,
        'aeble' => true,
        'agurk' => true,
    ];

    $tokens = shoppingMatchTokens($value);
    $hits = [];
    foreach ($tokens as $token) {
        if (isset($known[$token])) {
            $hits[$token] = true;
        }
    }
    return array_keys($hits);
}

function scoreShoppingTitleMatch(string $shoppingTitle, string $offerTitle): float
{
    $aNorm = normalizeShoppingMatchText($shoppingTitle);
    $bNorm = normalizeShoppingMatchText($offerTitle);
    if ($aNorm === '' || $bNorm === '') {
        return 0.0;
    }

    if ($aNorm === $bNorm) {
        return 100.0;
    }

    $aTokens = shoppingMatchTokens($aNorm);
    $bTokens = shoppingMatchTokens($bNorm);
    if ($aTokens === [] || $bTokens === []) {
        return 0.0;
    }

    $aSet = array_fill_keys($aTokens, true);
    $bSet = array_fill_keys($bTokens, true);
    $overlap = 0;
    foreach ($aSet as $token => $_) {
        if (isset($bSet[$token])) {
            $overlap++;
        }
    }

    $union = count($aSet) + count($bSet) - $overlap;
    $jaccard = $union > 0 ? ($overlap / $union) : 0.0;
    $coverage = count($aSet) > 0 ? ($overlap / count($aSet)) : 0.0;

    $score = ($coverage * 75.0) + ($jaccard * 25.0);
    if (str_contains($bNorm, $aNorm) || str_contains($aNorm, $bNorm)) {
        $score += 6.0;
    }
    return min($score, 100.0);
}

function resolveBestOfferForShoppingItem(PDO $pdo, string $productName, string $preferredStore = ''): ?array
{
    $normalizedName = normalizeShoppingMatchText($productName);
    if ($normalizedName === '') {
        return null;
    }

    $normalizedStore = normalizeShoppingMatchText($preferredStore);

    $sql = 'SELECT id, store_name, title, price, valid_to, created_at
            FROM store_offers
            WHERE (valid_to IS NULL OR valid_to >= CURDATE())';
    $params = [];

    if ($normalizedStore !== '') {
        $sql .= ' AND LOWER(TRIM(store_name)) = LOWER(TRIM(?))';
        $params[] = $preferredStore;
    }

    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $offers = $stmt->fetchAll() ?: [];

    $best = null;
    foreach ($offers as $offer) {
        $title = (string) ($offer['title'] ?? '');
        $score = scoreShoppingTitleMatch($productName, $title);
        if ($score < 58.0) {
            continue;
        }

        $strongOverlap = hasStrongTokenOverlap($productName, $title);
        if (!$strongOverlap && $score < 92.0) {
            continue;
        }

        $shoppingKeywords = knownFoodKeywords($productName);
        $offerKeywords = knownFoodKeywords($title);
        if ($shoppingKeywords !== [] && $offerKeywords !== []) {
            $intersect = array_intersect($shoppingKeywords, $offerKeywords);
            if ($intersect === [] && $score < 99.0) {
                continue;
            }
        }

        if ($best === null || $score > (float) $best['score']) {
            $best = [
                'offer_id' => isset($offer['id']) ? (int) $offer['id'] : null,
                'offer_price' => isset($offer['price']) ? (float) $offer['price'] : null,
                'offer_valid_to' => $offer['valid_to'] !== null ? (string) $offer['valid_to'] : null,
                'offer_store' => (string) ($offer['store_name'] ?? ''),
                'offer_title' => $title,
                'offer_source' => 'fuzzy_fallback',
                'score' => $score,
            ];
        }
    }

    return $best;
}

function handleRecipeList(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    try {
        $stmt = $pdo->prepare(
            'SELECT id, owner_household_id, title, description, source_type, source_reference, is_shared, created_at, updated_at
             FROM recipes
             WHERE owner_household_id = ?
             ORDER BY title ASC, id ASC'
        );
        $stmt->execute([$householdId]);

        response(200, [
            'status' => 'ok',
            'recipes' => $stmt->fetchAll() ?: [],
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleShoppingListAddItems(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $items = $data['items'] ?? [];

    if (!is_array($items) || count($items) === 0) {
        response(400, ['error' => 'Missing or empty items array']);
        return;
    }

    try {
        $pdo->beginTransaction();
        $hasOfferIdColumn = shoppingListItemsHasColumn($pdo, 'offer_id');

        // Get or create the open shopping list for this household
        $stmt = $pdo->prepare(
            'SELECT id FROM shopping_lists
             WHERE household_id = ? AND status = "open"
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$householdId]);
        $shoppingList = $stmt->fetch();

        if (!$shoppingList) {
            // Create a new shopping list
            $stmt = $pdo->prepare(
                'INSERT INTO shopping_lists (household_id, title, status, created_by_user_id)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $householdId,
                'Indkøbsseddel ' . date('d. M Y'),
                'open',
                $session['user_id'] ?? null,
            ]);
            $shoppingListId = (int) $pdo->lastInsertId();
        } else {
            $shoppingListId = (int) $shoppingList['id'];
        }

        $addedCount = 0;
        foreach ($items as $item) {
            $productName = trim((string) ($item['title'] ?? ''));
            $preferredStore = trim((string) ($item['store'] ?? ''));
            $productId = (int) ($item['productId'] ?? 0);
            $offerId = (int) ($item['offerId'] ?? 0);
            $offerPrice = null;
            $offerValidUntil = null;

            if ($productId > 0) {
                $productStmt = $pdo->prepare(
                    'SELECT p.id, p.name
                     FROM products p
                     INNER JOIN household_inventory hi ON hi.product_id = p.id
                     WHERE hi.household_id = ? AND p.id = ?
                     LIMIT 1'
                );
                $productStmt->execute([$householdId, $productId]);
                $productRow = $productStmt->fetch();
                if ($productRow) {
                    $productName = trim((string) ($productRow['name'] ?? $productName));
                } else {
                    $productId = 0;
                }
            }

            if ($productName === '') {
                continue;
            }

            if ($offerId > 0) {
                $offerStmt = $pdo->prepare(
                    'SELECT price, valid_to
                     FROM store_offers
                     WHERE id = ?
                     LIMIT 1'
                );
                $offerStmt->execute([$offerId]);
                $offerRow = $offerStmt->fetch();
                if ($offerRow) {
                    $offerPrice = $offerRow['price'] !== null ? (float) $offerRow['price'] : null;
                    $offerValidUntil = $offerRow['valid_to'] !== null ? (string) $offerRow['valid_to'] : null;
                }
            }

            // Check if item already exists in this shopping list with same name and store
            if ($productId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT id FROM shopping_list_items
                     WHERE shopping_list_id = ?
                       AND product_id = ?
                       AND ((preferred_store IS NULL AND ? IS NULL) OR preferred_store = ?)
                     LIMIT 1'
                );
                $storeValue = $preferredStore !== '' ? $preferredStore : null;
                $stmt->execute([$shoppingListId, $productId, $storeValue, $storeValue]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id FROM shopping_list_items
                     WHERE shopping_list_id = ? AND product_name = ? AND preferred_store = ?
                     LIMIT 1'
                );
                $stmt->execute([$shoppingListId, $productName, $preferredStore]);
            }
            if ($stmt->fetch()) {
                continue; // Skip duplicate
            }

            // Add item to shopping list
            if ($hasOfferIdColumn) {
                $stmt = $pdo->prepare(
                    'INSERT INTO shopping_list_items (shopping_list_id, product_id, product_name, quantity, preferred_store, offer_id, offer_price, offer_valid_until)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $shoppingListId,
                    $productId > 0 ? $productId : null,
                    $productName,
                    1,
                    $preferredStore !== '' ? $preferredStore : null,
                    $offerId > 0 ? $offerId : null,
                    $offerPrice,
                    $offerValidUntil,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO shopping_list_items (shopping_list_id, product_id, product_name, quantity, preferred_store, offer_price, offer_valid_until)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $shoppingListId,
                    $productId > 0 ? $productId : null,
                    $productName,
                    1,
                    $preferredStore !== '' ? $preferredStore : null,
                    $offerPrice,
                    $offerValidUntil,
                ]);
            }
            $addedCount++;
        }

        $pdo->commit();

        response(200, [
            'status' => 'ok',
            'message' => "Added $addedCount item(s) to shopping list",
            'shopping_list_id' => $shoppingListId,
            'items_added' => $addedCount,
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

function handleShoppingListSetItemChecked(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $itemId = (int) ($data['item_id'] ?? 0);
    $isChecked = !empty($data['is_checked']) ? 1 : 0;

    if ($itemId <= 0) {
        response(400, ['error' => 'Missing or invalid item_id']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             SET si.is_checked = ?
             WHERE si.id = ?
               AND sl.household_id = ?
               AND sl.status IN ("open", "in_progress")'
        );
        $stmt->execute([$isChecked, $itemId, $householdId]);

        if ($stmt->rowCount() < 1) {
            response(404, ['error' => 'Shopping list item not found']);
            return;
        }

        response(200, [
            'status' => 'ok',
            'item_id' => $itemId,
            'is_checked' => (bool) $isChecked,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleShoppingListRemoveItem(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $itemId = (int) ($data['item_id'] ?? 0);

    if ($itemId <= 0) {
        response(400, ['error' => 'Missing or invalid item_id']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'DELETE si FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             WHERE si.id = ?
               AND sl.household_id = ?
               AND sl.status IN ("open", "in_progress")'
        );
        $stmt->execute([$itemId, $householdId]);

        if ($stmt->rowCount() < 1) {
            response(404, ['error' => 'Shopping list item not found']);
            return;
        }

        response(200, [
            'status' => 'ok',
            'item_id' => $itemId,
            'removed' => true,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}
