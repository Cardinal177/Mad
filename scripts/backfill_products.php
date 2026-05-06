<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/handlers/ScanHandler.php';

function refreshProductFromOff(PDO $pdo, array $product): bool
{
    $barcode = (string) ($product['barcode'] ?? '');
    if ($barcode === '') {
        return false;
    }

    $needsRefresh = str_starts_with((string) ($product['name'] ?? ''), 'Scanned Product ')
        || ($product['brand'] ?? null) === null
        || ($product['image_url'] ?? null) === null
        || ($product['nutrition_json'] ?? null) === null;

    if (!$needsRefresh) {
        return false;
    }

    $offProduct = fetchOpenFoodFactsProduct($barcode);
    if (!$offProduct) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE products
         SET name = ?, brand = ?, image_url = ?, nutrition_json = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $offProduct['name'],
        $offProduct['brand'] ?? null,
        $offProduct['image_url'] ?? null,
        json_encode($offProduct['nutrition_json'] ?? null, JSON_UNESCAPED_SLASHES),
        (int) $product['id'],
    ]);

    return true;
}

function findOrCreateCanonicalProduct(PDO $pdo, string $barcode): array
{
    $stmt = $pdo->prepare(
        'SELECT id, barcode, name, brand, image_url, nutrition_json
         FROM products
         WHERE barcode = ?
         LIMIT 1'
    );
    $stmt->execute([$barcode]);
    $product = $stmt->fetch();
    if ($product) {
        return $product;
    }

    $offProduct = fetchOpenFoodFactsProduct($barcode);
    $name = $offProduct['name'] ?? placeholderProductName($barcode);
    $brand = $offProduct['brand'] ?? null;
    $imageUrl = $offProduct['image_url'] ?? null;
    $nutritionJson = isset($offProduct['nutrition_json']) ? json_encode($offProduct['nutrition_json'], JSON_UNESCAPED_SLASHES) : null;

    $stmt = $pdo->prepare(
        'INSERT INTO products (barcode, name, brand, image_url, nutrition_json)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$barcode, $name, $brand, $imageUrl, $nutritionJson]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'barcode' => $barcode,
        'name' => $name,
        'brand' => $brand,
        'image_url' => $imageUrl,
        'nutrition_json' => $nutritionJson,
    ];
}

function mergeInventory(PDO $pdo, int $sourceProductId, int $targetProductId): void
{
    $stmt = $pdo->prepare(
        'SELECT id, household_id, location_id, quantity, minimum_quantity
         FROM household_inventory
         WHERE product_id = ?'
    );
    $stmt->execute([$sourceProductId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $lookup = $pdo->prepare(
            'SELECT id, quantity, minimum_quantity
             FROM household_inventory
             WHERE household_id = ? AND location_id = ? AND product_id = ?
             LIMIT 1'
        );
        $lookup->execute([
            (int) $row['household_id'],
            (int) $row['location_id'],
            $targetProductId,
        ]);
        $existing = $lookup->fetch();

        if ($existing) {
            $update = $pdo->prepare(
                'UPDATE household_inventory
                 SET quantity = ?, minimum_quantity = ?
                 WHERE id = ?'
            );
            $update->execute([
                (float) $existing['quantity'] + (float) $row['quantity'],
                max((float) $existing['minimum_quantity'], (float) $row['minimum_quantity']),
                (int) $existing['id'],
            ]);

            $delete = $pdo->prepare('DELETE FROM household_inventory WHERE id = ?');
            $delete->execute([(int) $row['id']]);
            continue;
        }

        $update = $pdo->prepare('UPDATE household_inventory SET product_id = ? WHERE id = ?');
        $update->execute([$targetProductId, (int) $row['id']]);
    }
}

function mergeProductReferences(PDO $pdo, int $sourceProductId, int $targetProductId): void
{
    mergeInventory($pdo, $sourceProductId, $targetProductId);

    $simpleUpdates = [
        'inventory_movements',
        'recipe_ingredients',
        'shopping_list_items',
        'store_offers',
    ];

    foreach ($simpleUpdates as $table) {
        $stmt = $pdo->prepare("UPDATE {$table} SET product_id = ? WHERE product_id = ?");
        $stmt->execute([$targetProductId, $sourceProductId]);
    }

    $delete = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $delete->execute([$sourceProductId]);
}

$pdo = db();

$stmt = $pdo->query(
    'SELECT id, barcode, name, brand, image_url, nutrition_json
     FROM products
     ORDER BY id ASC'
);
$products = $stmt->fetchAll();

$stats = [
    'refreshed' => 0,
    'merged' => 0,
    'created' => 0,
    'checked' => count($products),
];

foreach ($products as $product) {
    $barcode = (string) ($product['barcode'] ?? '');
    if ($barcode === '') {
        continue;
    }

    $normalized = normalizeBarcode($barcode);

    try {
        $pdo->beginTransaction();

        if ($normalized !== $barcode) {
            $canonical = findOrCreateCanonicalProduct($pdo, $normalized);
            if ((int) $canonical['id'] !== (int) $product['id']) {
                mergeProductReferences($pdo, (int) $product['id'], (int) $canonical['id']);
                echo 'Merged barcode ' . $barcode . ' -> ' . $normalized . PHP_EOL;
                $stats['merged']++;
            }

            if ((int) $canonical['id'] > (int) $product['id']) {
                $stats['created']++;
            }

            refreshProductFromOff($pdo, $canonical);
            $pdo->commit();
            continue;
        }

        if (refreshProductFromOff($pdo, $product)) {
            echo 'Refreshed product #' . $product['id'] . ' (' . $barcode . ')' . PHP_EOL;
            $stats['refreshed']++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, 'Failed for barcode ' . $barcode . ': ' . $e->getMessage() . PHP_EOL);
    }
}

echo PHP_EOL;
echo 'Checked: ' . $stats['checked'] . PHP_EOL;
echo 'Refreshed: ' . $stats['refreshed'] . PHP_EOL;
echo 'Merged: ' . $stats['merged'] . PHP_EOL;
echo 'Created canonical products: ' . $stats['created'] . PHP_EOL;