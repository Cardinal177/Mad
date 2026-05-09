#!/usr/bin/env php
<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';
require_once $baseDir . '/src/handlers/ScanHandler.php';

$householdIdArg = isset($argv[1]) ? (int) $argv[1] : 0;
$limit = isset($argv[2]) ? max((int) $argv[2], 1) : 500;

try {
    $pdo = db();

    if (!shoppingListItemsHasColumn($pdo, 'offer_id')) {
        fwrite(STDERR, "Missing shopping_list_items.offer_id. Run scripts/migrate_shopping_offer_link.php first.\n");
        exit(1);
    }

    $params = [];
    $sql = 'SELECT
                si.id,
                si.product_name,
                si.preferred_store,
                sl.household_id
            FROM shopping_list_items si
            INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
            WHERE sl.status IN ("open", "in_progress")
              AND (si.offer_id IS NULL OR si.offer_price IS NULL)';

    if ($householdIdArg > 0) {
        $sql .= ' AND sl.household_id = ?';
        $params[] = $householdIdArg;
    }

    $sql .= ' ORDER BY si.id ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $checked = 0;
    $updated = 0;

    $pdo->beginTransaction();
    $update = $pdo->prepare(
        'UPDATE shopping_list_items
         SET offer_id = ?,
             offer_price = ?,
             offer_valid_until = ?
         WHERE id = ?'
    );

    foreach ($rows as $row) {
        $checked++;
        $id = (int) ($row['id'] ?? 0);
        $name = (string) ($row['product_name'] ?? '');
        $store = (string) ($row['preferred_store'] ?? '');

        if ($id <= 0 || $name === '') {
            continue;
        }

        $match = resolveBestOfferForShoppingItem($pdo, $name, $store);
        if (!$match || !isset($match['offer_price']) || $match['offer_price'] === null) {
            continue;
        }

        $update->execute([
            isset($match['offer_id']) ? (int) $match['offer_id'] : null,
            (float) $match['offer_price'],
            $match['offer_valid_to'] ?? null,
            $id,
        ]);
        if ($update->rowCount() > 0) {
            $updated++;
        }
    }

    $pdo->commit();

    echo "Backfill complete\n";
    echo "Checked: {$checked}\n";
    echo "Updated: {$updated}\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Backfill failed: ' . $e->getMessage() . "\n");
    exit(1);
}
