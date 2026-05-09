<?php

declare(strict_types=1);

// Valid product_type ENUM values
const PRODUCT_TYPES = [
    'tørvare', 'ferskvare', 'mejeri', 'kød', 'fisk',
    'frugt_groent', 'frostvare', 'krydderier', 'drikke',
    'konserves', 'brød', 'andet',
];

/**
 * Ask AI to classify a product and estimate shelf life.
 * Returns ['product_type' => string, 'estimated_shelf_days' => int|null, 'ai_note' => string]
 * Falls back gracefully if AI is disabled or call fails.
 */
function aiEnrichIngredient(string $name, string $brand, array $categories): array
{
    if (!function_exists('isAiEnabled') || !isAiEnabled()) {
        return ['product_type' => 'andet', 'estimated_shelf_days' => null, 'ai_note' => ''];
    }

    $categoryList = implode(', ', $categories);
    $validTypes = implode(', ', PRODUCT_TYPES);

    $systemPrompt = 'Du er en dansk madvarekategoriserings-assistent. Svar KUN med gyldig JSON og intet andet.';

    $userPrompt = "Kategoriser dette produkt:\n" .
        "Navn: {$name}\n" .
        "Brand: {$brand}\n" .
        "OFF-kategorier: {$categoryList}\n\n" .
        "Returner JSON præcis som:\n" .
        "{\n" .
        "  \"product_type\": \"<EN AF: {$validTypes}>\",\n" .
        "  \"estimated_shelf_days\": <heltal dage fra køb til typisk udløb, eller null hvis usikkert>,\n" .
        "  \"ai_note\": \"<kort dansk forklaring, max 60 tegn>\"\n" .
        "}";

    $result = callAnthropic($systemPrompt, $userPrompt);

    if (!$result['ok']) {
        return ['product_type' => 'andet', 'estimated_shelf_days' => null, 'ai_note' => ''];
    }

    // Strip potential markdown code fences
    $text = trim((string) ($result['text'] ?? ''));
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        return ['product_type' => 'andet', 'estimated_shelf_days' => null, 'ai_note' => ''];
    }

    $type = (string) ($decoded['product_type'] ?? '');
    if (!in_array($type, PRODUCT_TYPES, true)) {
        $type = 'andet';
    }

    $shelfDays = isset($decoded['estimated_shelf_days']) && is_numeric($decoded['estimated_shelf_days'])
        ? max(1, (int) $decoded['estimated_shelf_days'])
        : null;

    $note = mb_substr(trim((string) ($decoded['ai_note'] ?? '')), 0, 120);

    return [
        'product_type' => $type,
        'estimated_shelf_days' => $shelfDays,
        'ai_note' => $note,
    ];
}

function resolveHouseholdLocationId(PDO $pdo, int $householdId, ?int $requestedLocationId): int
{
    if ($requestedLocationId !== null && $requestedLocationId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM household_locations WHERE id = ? AND household_id = ? LIMIT 1');
        $stmt->execute([$requestedLocationId, $householdId]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        response(403, ['error' => 'Forbidden location']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM household_locations WHERE household_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$householdId]);
    $row = $stmt->fetch();
    if (!$row) {
        response(404, ['error' => 'No location for household']);
        exit;
    }

    return (int) $row['id'];
}

function handleIngredientLookup(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $barcode = trim((string) ($_GET['barcode'] ?? ''));
    if ($barcode === '') {
        response(400, ['error' => 'barcode required']);
        return;
    }

    $product = fetchOpenFoodFactsProduct($barcode);
    if (!$product) {
        response(404, ['error' => 'No product found in lookup source']);
        return;
    }

    $name = (string) ($product['name'] ?? '');
    $brand = (string) ($product['brand'] ?? '');
    $categories = is_array($product['categories'] ?? null) ? $product['categories'] : [];

    $enrichment = aiEnrichIngredient($name, $brand, $categories);

    response(200, [
        'status' => 'ok',
        'lookup_source' => 'openfoodfacts',
        'ai_enriched' => isAiEnabled(),
        'product' => [
            'barcode' => $barcode,
            'name' => $name,
            'brand' => $brand,
            'image_url' => $product['image_url'] ?? '',
            'nutrition_json' => $product['nutrition_json'] ?? null,
            'product_type' => $enrichment['product_type'],
            'estimated_shelf_days' => $enrichment['estimated_shelf_days'],
            'ai_note' => $enrichment['ai_note'],
        ],
    ]);
}

function handleIngredientCreate(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $data = parseJsonInput() ?? [];

    $requestedHouseholdId = isset($data['household_id']) ? (int) $data['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);
    $locationId = resolveHouseholdLocationId($pdo, $householdId, isset($data['location_id']) ? (int) $data['location_id'] : null);

    $barcode = trim((string) ($data['barcode'] ?? ''));
    $requestedProductId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
    $name = trim((string) ($data['name'] ?? ''));
    $brand = trim((string) ($data['brand'] ?? ''));
    $imageUrl = trim((string) ($data['image_url'] ?? ''));
    $productType = trim((string) ($data['product_type'] ?? 'andet'));
    $minimumQuantity = isset($data['minimum_quantity']) ? (float) $data['minimum_quantity'] : 0.0;
    $quantity = isset($data['quantity']) ? (float) $data['quantity'] : 0.0;
    $weightGrams = null;
    if (array_key_exists('weight_grams', $data) && $data['weight_grams'] !== '') {
        $weightGrams = max(0, (int) $data['weight_grams']);
    }

    $storeName = trim((string) ($data['store_name'] ?? ''));
    $price = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;
    $offerStore = trim((string) ($data['offer_store'] ?? $storeName));
    $offerPrice = isset($data['offer_price']) && $data['offer_price'] !== '' ? (float) $data['offer_price'] : null;
    $offerValidTo = trim((string) ($data['offer_valid_to'] ?? ''));

    if ($name === '' && $barcode === '') {
        response(400, ['error' => 'name or barcode required']);
        return;
    }

    if ($name === '' && $barcode !== '') {
        $lookup = fetchOpenFoodFactsProduct($barcode);
        if ($lookup) {
            $name = (string) ($lookup['name'] ?? '');
            if ($brand === '') {
                $brand = (string) ($lookup['brand'] ?? '');
            }
            if ($imageUrl === '') {
                $imageUrl = (string) ($lookup['image_url'] ?? '');
            }
        }
    }

    if ($name === '') {
        $name = placeholderProductName($barcode !== '' ? $barcode : ('manual-' . date('His')));
    }

    if ($offerValidTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $offerValidTo)) {
        response(400, ['error' => 'offer_valid_to must be YYYY-MM-DD']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $productId = null;
        if ($requestedProductId > 0) {
            $stmt = $pdo->prepare(
                'SELECT p.id, p.barcode
                 FROM products p
                 INNER JOIN household_inventory hi ON hi.product_id = p.id
                 WHERE p.id = ? AND hi.household_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$requestedProductId, $householdId]);
            $existingProduct = $stmt->fetch();
            if (!$existingProduct) {
                response(404, ['error' => 'Produktet findes ikke i denne husstand']);
                return;
            }

            $productId = (int) $existingProduct['id'];
            if ($barcode === '') {
                $barcode = (string) ($existingProduct['barcode'] ?? '');
            }
        }

        if ($barcode !== '') {
            $stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ? LIMIT 1');
            $stmt->execute([$barcode]);
            $existing = $stmt->fetch();
            if ($existing) {
                if ($productId !== null && $productId !== (int) $existing['id']) {
                    response(409, ['error' => 'Barcode findes allerede på et andet produkt']);
                    return;
                }
                $productId = (int) $existing['id'];

            }
        }

        if ($productId !== null) {
            if (scanProductsHasColumn($pdo, 'weight_grams')) {
                $stmt = $pdo->prepare(
                    'UPDATE products
                     SET barcode = ?,
                         name = ?,
                         brand = ?,
                         image_url = ?,
                         product_type = ?,
                         weight_grams = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $barcode !== '' ? $barcode : null,
                    $name,
                    $brand !== '' ? $brand : null,
                    $imageUrl !== '' ? $imageUrl : null,
                    $productType,
                    $weightGrams,
                    $productId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE products
                     SET barcode = ?,
                         name = ?,
                         brand = ?,
                         image_url = ?,
                         product_type = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $barcode !== '' ? $barcode : null,
                    $name,
                    $brand !== '' ? $brand : null,
                    $imageUrl !== '' ? $imageUrl : null,
                    $productType,
                    $productId,
                ]);
            }
        }

        if ($productId === null) {
            if (scanProductsHasColumn($pdo, 'weight_grams')) {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (barcode, name, brand, image_url, product_type, weight_grams)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $barcode !== '' ? $barcode : null,
                    $name,
                    $brand !== '' ? $brand : null,
                    $imageUrl !== '' ? $imageUrl : null,
                    $productType,
                    $weightGrams,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (barcode, name, brand, image_url, product_type)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $barcode !== '' ? $barcode : null,
                    $name,
                    $brand !== '' ? $brand : null,
                    $imageUrl !== '' ? $imageUrl : null,
                    $productType,
                ]);
            }
            $productId = (int) $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare(
            'SELECT id, quantity, minimum_quantity
             FROM household_inventory
             WHERE household_id = ? AND location_id = ? AND product_id = ?
             LIMIT 1'
        );
        $stmt->execute([$householdId, $locationId, $productId]);
        $inventory = $stmt->fetch();

        if ($inventory) {
            $newQuantity = isset($data['quantity']) ? $quantity : (float) $inventory['quantity'];
            $newMinimum = isset($data['minimum_quantity']) ? $minimumQuantity : (float) $inventory['minimum_quantity'];
            $stmt = $pdo->prepare('UPDATE household_inventory SET quantity = ?, minimum_quantity = ? WHERE id = ?');
            $stmt->execute([$newQuantity, $newMinimum, (int) $inventory['id']]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO household_inventory (household_id, location_id, product_id, quantity, minimum_quantity)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$householdId, $locationId, $productId, $quantity, $minimumQuantity]);
        }

        if ($price !== null && $storeName !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO store_offers (store_name, product_id, title, price, valid_from, valid_to)
                 VALUES (?, ?, ?, ?, CURDATE(), NULL)'
            );
            $stmt->execute([$storeName, $productId, 'Standardpris', $price]);
        }

        if ($offerPrice !== null && $offerStore !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO store_offers (store_name, product_id, title, price, valid_from, valid_to)
                 VALUES (?, ?, ?, ?, CURDATE(), ?)'
            );
            $stmt->execute([$offerStore, $productId, 'Tilbud', $offerPrice, $offerValidTo !== '' ? $offerValidTo : null]);
        }

        $pdo->commit();

        response(201, [
            'status' => 'ok',
            'ingredient' => [
                'product_id' => $productId,
                'barcode' => $barcode,
                'name' => $name,
                'brand' => $brand,
                'image_url' => $imageUrl,
                'product_type' => $productType,
                'weight_grams' => $weightGrams,
                'household_id' => $householdId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'minimum_quantity' => $minimumQuantity,
            ],
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
