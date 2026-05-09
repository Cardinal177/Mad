#!/usr/bin/env php
<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';
require_once $baseDir . '/src/handlers/ScanHandler.php';

$householdId = isset($argv[1]) ? max((int) $argv[1], 1) : 1;
$limit = isset($argv[2]) ? max((int) $argv[2], 1) : 200;

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, title, status, created_at
         FROM shopping_lists
         WHERE household_id = ?
           AND status IN ("open", "in_progress")
         ORDER BY CASE WHEN status = "open" THEN 0 ELSE 1 END, created_at DESC
         LIMIT 1'
    );
    $stmt->execute([$householdId]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$list) {
        echo "No open shopping list for household {$householdId}.\n";
        exit(0);
    }

    $stmt = $pdo->prepare(
        'SELECT id, product_name, preferred_store, offer_price
         FROM shopping_list_items
         WHERE shopping_list_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([(int) $list['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $total = count($items);
    $withStoredPrice = 0;
    $resolvedByFallback = 0;
    $unresolved = [];
    $resolvedExamples = [];

    foreach ($items as $row) {
        $itemId = (int) ($row['id'] ?? 0);
        $name = (string) ($row['product_name'] ?? '');
        $store = (string) ($row['preferred_store'] ?? '');
        $storedPrice = $row['offer_price'];

        if ($storedPrice !== null) {
            $withStoredPrice++;
            continue;
        }

        $match = resolveBestOfferForShoppingItem($pdo, $name, $store);
        if ($match && $match['offer_price'] !== null) {
            $resolvedByFallback++;
            if (count($resolvedExamples) < 15) {
                $resolvedExamples[] = [
                    'id' => $itemId,
                    'name' => $name,
                    'store' => $store,
                    'price' => (float) $match['offer_price'],
                    'offer_store' => (string) ($match['offer_store'] ?? ''),
                    'offer_title' => (string) ($match['offer_title'] ?? ''),
                    'score' => (float) ($match['score'] ?? 0.0),
                ];
            }
            continue;
        }

        $unresolved[] = [
            'id' => $itemId,
            'name' => $name,
            'store' => $store,
        ];
    }

    $coverage = $total > 0 ? (($withStoredPrice + $resolvedByFallback) / $total) * 100.0 : 0.0;

    echo "Shopping list test for household {$householdId}\n";
    echo "List ID: " . (int) $list['id'] . " | " . (string) ($list['title'] ?? '') . "\n";
    echo "Items tested: {$total}\n";
    echo "Stored price: {$withStoredPrice}\n";
    echo "Resolved by fallback: {$resolvedByFallback}\n";
    echo "Unresolved: " . count($unresolved) . "\n";
    echo "Coverage: " . number_format($coverage, 1) . "%\n";

    if ($resolvedExamples !== []) {
        echo "\nResolved examples:\n";
        foreach ($resolvedExamples as $ex) {
            echo "- #{$ex['id']} {$ex['name']} [{$ex['store']}] => "
                . number_format((float) $ex['price'], 2) . " kr"
                . " (offer: {$ex['offer_title']} @ {$ex['offer_store']}, score "
                . number_format((float) $ex['score'], 1) . ")\n";
        }
    }

    if ($unresolved !== []) {
        echo "\nUnresolved items (first 20):\n";
        foreach (array_slice($unresolved, 0, 20) as $row) {
            echo "- #" . (int) $row['id'] . " " . $row['name'] . " [" . ($row['store'] ?: '-') . "]\n";
        }
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Test failed: ' . $e->getMessage() . "\n");
    exit(1);
}
