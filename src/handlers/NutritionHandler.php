<?php

declare(strict_types=1);

const FRIDA_REFERENCE_FOODS = [
    [
        'code' => 'FRIDA-1001',
        'name' => 'Havregryn',
        'keywords' => ['havregryn', 'oats', 'oatmeal'],
        'nutrition' => [
            'per' => '100g',
            'energy_kcal' => 379,
            'fat_g' => 6.5,
            'carbohydrates_g' => 67.7,
            'sugars_g' => 1.0,
            'protein_g' => 13.2,
            'salt_g' => 0.01,
        ],
    ],
    [
        'code' => 'FRIDA-1002',
        'name' => 'Pasta, torret',
        'keywords' => ['pasta', 'spaghetti', 'penne', 'fusilli'],
        'nutrition' => [
            'per' => '100g',
            'energy_kcal' => 357,
            'fat_g' => 1.5,
            'carbohydrates_g' => 72.0,
            'sugars_g' => 3.0,
            'protein_g' => 12.0,
            'salt_g' => 0.01,
        ],
    ],
    [
        'code' => 'FRIDA-1003',
        'name' => 'Kylling, filet',
        'keywords' => ['kylling', 'chicken', 'filet'],
        'nutrition' => [
            'per' => '100g',
            'energy_kcal' => 114,
            'fat_g' => 1.6,
            'carbohydrates_g' => 0.0,
            'sugars_g' => 0.0,
            'protein_g' => 24.0,
            'salt_g' => 0.15,
        ],
    ],
    [
        'code' => 'FRIDA-1004',
        'name' => 'Tomat, frisk',
        'keywords' => ['tomat', 'tomato'],
        'nutrition' => [
            'per' => '100g',
            'energy_kcal' => 18,
            'fat_g' => 0.2,
            'carbohydrates_g' => 3.9,
            'sugars_g' => 2.6,
            'protein_g' => 0.9,
            'salt_g' => 0.01,
        ],
    ],
    [
        'code' => 'FRIDA-1005',
        'name' => 'Mlk 1.5%',
        'keywords' => ['maelk', 'mælk', 'milk'],
        'nutrition' => [
            'per' => '100g',
            'energy_kcal' => 46,
            'fat_g' => 1.5,
            'carbohydrates_g' => 4.9,
            'sugars_g' => 4.9,
            'protein_g' => 3.5,
            'salt_g' => 0.1,
        ],
    ],
];

function productsHasNutritionColumn(PDO $pdo, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $pdo->prepare('SHOW COLUMNS FROM products LIKE ?');
    $stmt->execute([$column]);
    $cache[$column] = (bool) $stmt->fetch();

    return $cache[$column];
}

function ensureNutritionColumnsOrRespond(PDO $pdo): bool
{
    $requiredSql = [
        'nutrition_source' => "ALTER TABLE products ADD COLUMN nutrition_source ENUM('unknown', 'off_label', 'frida_dtu', 'manual', 'placeholder') NOT NULL DEFAULT 'unknown' AFTER nutrition_json",
        'nutrition_confidence' => 'ALTER TABLE products ADD COLUMN nutrition_confidence DECIMAL(4,3) DEFAULT NULL AFTER nutrition_source',
        'frida_food_code' => 'ALTER TABLE products ADD COLUMN frida_food_code VARCHAR(64) DEFAULT NULL AFTER nutrition_confidence',
        'nutrition_updated_at' => 'ALTER TABLE products ADD COLUMN nutrition_updated_at DATETIME DEFAULT NULL AFTER frida_food_code',
    ];

    foreach ($requiredSql as $column => $sql) {
        if (productsHasNutritionColumn($pdo, $column)) {
            continue;
        }

        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            response(409, [
                'error' => 'Nutrition metadata columns missing',
                'missing_column' => $column,
                'details' => $e->getMessage(),
                'hint' => 'Run php scripts/migrate_nutrition_quality.php',
            ]);
            return false;
        }
    }

    foreach (array_keys($requiredSql) as $column) {
        if (!productsHasNutritionColumn($pdo, $column)) {
            response(409, [
                'error' => 'Nutrition metadata columns missing',
                'missing_column' => $column,
                'hint' => 'Run php scripts/migrate_nutrition_quality.php',
            ]);
            return false;
        }
    }

    return true;
}

function normalizeMatchText(string $value): string
{
    $value = mb_strtolower($value);
    $value = strtr($value, ['æ' => 'ae', 'ø' => 'oe', 'å' => 'aa']);
    $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

function tokenizeMatchText(string $value): array
{
    $normalized = normalizeMatchText($value);
    if ($normalized === '') {
        return [];
    }

    $parts = explode(' ', $normalized);
    $parts = array_values(array_filter($parts, static fn(string $token): bool => strlen($token) >= 3));

    return array_values(array_unique($parts));
}

function scoreReferenceMatch(array $product, array $reference): float
{
    $name = (string) ($product['name'] ?? '');
    $brand = (string) ($product['brand'] ?? '');
    $text = normalizeMatchText($name . ' ' . $brand);
    $tokens = tokenizeMatchText($text);

    if ($tokens === []) {
        return 0.0;
    }

    $score = 0.0;
    $keywordHits = 0;

    foreach ($reference['keywords'] as $keyword) {
        $kw = normalizeMatchText((string) $keyword);
        if ($kw === '') {
            continue;
        }

        if (str_contains($text, $kw)) {
            $score += 0.35;
            $keywordHits++;
            continue;
        }

        $kwTokens = tokenizeMatchText($kw);
        $overlap = count(array_intersect($tokens, $kwTokens));
        if ($overlap > 0) {
            $score += min(0.2, 0.08 * $overlap);
            $keywordHits++;
        }
    }

    if ($keywordHits > 1) {
        $score += 0.15;
    }

    return min(1.0, $score);
}

function findBestNutritionMatch(array $product): ?array
{
    $best = null;
    $bestScore = 0.0;

    foreach (FRIDA_REFERENCE_FOODS as $reference) {
        $score = scoreReferenceMatch($product, $reference);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $reference;
        }
    }

    if ($best === null) {
        return null;
    }

    $best['score'] = round($bestScore, 3);
    return $best;
}

function handleNutritionQualitySummary(PDO $pdo): void
{
    requirePlatformAdmin($pdo);
    if (!ensureNutritionColumnsOrRespond($pdo)) {
        return;
    }

    $totals = $pdo->query(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN nutrition_source = "frida_dtu" THEN 1 ELSE 0 END) AS frida_count,
            SUM(CASE WHEN nutrition_source = "off_label" THEN 1 ELSE 0 END) AS off_count,
            SUM(CASE WHEN nutrition_source = "placeholder" THEN 1 ELSE 0 END) AS placeholder_count,
            SUM(CASE WHEN nutrition_json IS NULL THEN 1 ELSE 0 END) AS no_nutrition,
            SUM(CASE WHEN nutrition_confidence IS NULL OR nutrition_confidence < 0.70 THEN 1 ELSE 0 END) AS low_confidence
         FROM products'
    )->fetch() ?: [];

    $stmt = $pdo->prepare(
        'SELECT id, barcode, name, brand, nutrition_source, nutrition_confidence, frida_food_code, updated_at
         FROM products
         WHERE nutrition_json IS NULL
            OR nutrition_source IN ("placeholder", "unknown")
            OR nutrition_confidence IS NULL
            OR nutrition_confidence < 0.70
         ORDER BY updated_at DESC
         LIMIT 50'
    );
    $stmt->execute();
    $review = $stmt->fetchAll() ?: [];

    response(200, [
        'status' => 'ok',
        'quality' => [
            'total_products' => (int) ($totals['total'] ?? 0),
            'frida_dtu' => (int) ($totals['frida_count'] ?? 0),
            'off_label' => (int) ($totals['off_count'] ?? 0),
            'placeholder' => (int) ($totals['placeholder_count'] ?? 0),
            'missing_nutrition' => (int) ($totals['no_nutrition'] ?? 0),
            'low_confidence' => (int) ($totals['low_confidence'] ?? 0),
        ],
        'needs_review' => $review,
    ]);
}

function handleNutritionMatchRun(PDO $pdo): void
{
    requirePlatformAdmin($pdo);
    if (!ensureNutritionColumnsOrRespond($pdo)) {
        return;
    }

    $body = parseJsonInput() ?? [];
    $limit = (int) ($body['limit'] ?? 40);
    $dryRun = (bool) ($body['dry_run'] ?? false);
    $minScore = (float) ($body['min_score'] ?? 0.55);

    if ($limit < 1 || $limit > 200) {
        $limit = 40;
    }
    if ($minScore < 0.2 || $minScore > 0.95) {
        $minScore = 0.55;
    }

    $stmt = $pdo->prepare(
        'SELECT id, barcode, name, brand, nutrition_source, nutrition_confidence
         FROM products
         WHERE nutrition_source IS NULL
            OR nutrition_source IN ("off_label", "placeholder", "unknown")
            OR nutrition_confidence IS NULL
            OR nutrition_confidence < 0.70
         ORDER BY updated_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll() ?: [];

    $matched = [];
    $updated = 0;

    foreach ($products as $product) {
        $best = findBestNutritionMatch($product);
        if ($best === null || (float) $best['score'] < $minScore) {
            continue;
        }

        $matched[] = [
            'product_id' => (int) $product['id'],
            'product_name' => (string) $product['name'],
            'frida_food_code' => (string) $best['code'],
            'frida_name' => (string) $best['name'],
            'score' => (float) $best['score'],
        ];

        if ($dryRun) {
            continue;
        }

        $up = $pdo->prepare(
            'UPDATE products
             SET nutrition_json = ?,
                 nutrition_source = "frida_dtu",
                 nutrition_confidence = ?,
                 frida_food_code = ?,
                 nutrition_updated_at = NOW()
             WHERE id = ?'
        );
        $up->execute([
            json_encode($best['nutrition'], JSON_UNESCAPED_SLASHES),
            $best['score'],
            $best['code'],
            (int) $product['id'],
        ]);

        $updated += $up->rowCount();
    }

    response(200, [
        'status' => 'ok',
        'dry_run' => $dryRun,
        'min_score' => $minScore,
        'scanned_products' => count($products),
        'matched_products' => count($matched),
        'updated_products' => $updated,
        'matches' => $matched,
    ]);
}
