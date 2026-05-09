#!/usr/bin/env php
<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';

try {
    $pdo = db();

    $stmt = $pdo->query(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'shopping_list_items'
           AND COLUMN_NAME = 'offer_id'
         LIMIT 1"
    );
    $hasOfferId = (bool) $stmt->fetchColumn();

    if (!$hasOfferId) {
        $pdo->exec('ALTER TABLE shopping_list_items ADD COLUMN offer_id BIGINT UNSIGNED DEFAULT NULL AFTER product_category');
        echo "Added column shopping_list_items.offer_id\n";
    } else {
        echo "Column shopping_list_items.offer_id already exists\n";
    }

    $stmt = $pdo->query(
        "SELECT 1
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'shopping_list_items'
           AND INDEX_NAME = 'idx_shopping_items_offer'
         LIMIT 1"
    );
    $hasIndex = (bool) $stmt->fetchColumn();

    if (!$hasIndex) {
        $pdo->exec('ALTER TABLE shopping_list_items ADD INDEX idx_shopping_items_offer (offer_id)');
        echo "Added index idx_shopping_items_offer\n";
    } else {
        echo "Index idx_shopping_items_offer already exists\n";
    }

    echo "Migration complete\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
