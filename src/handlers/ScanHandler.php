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
            $remaining = trim($remaining);
            // Also strip size suffixes like "35g", "500ml" from end
            $remaining = (string) preg_replace('/\s*\d+\s*(?:g|ml|l|cl|dl|kg|oz)\s*$/iu', '', $remaining);
            $remaining = trim($remaining);
            if (strlen($remaining) > 2) {
                return $remaining;
            }
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

function householdInventoryHasColumn(PDO $pdo, string $column): bool
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
    $stmt->execute(['household_inventory', $column]);
    $cache[$column] = (bool) $stmt->fetchColumn();

    return $cache[$column];
}

function tableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    $cache[$table] = (bool) $stmt->fetchColumn();

    return $cache[$table];
}

function recipesHasColumn(PDO $pdo, string $column): bool
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
    $stmt->execute(['recipes', $column]);
    $cache[$column] = (bool) $stmt->fetchColumn();

    return $cache[$column];
}

function parseIso8601DurationToMinutes(?string $duration): ?int
{
    $value = trim((string) $duration);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?$/i', $value, $matches) === 1) {
        $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
        $minutes = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $total = ($hours * 60) + $minutes;
        return $total > 0 ? $total : null;
    }

    return null;
}

function parseRecipeYieldToServings($recipeYield): ?int
{
    if (is_numeric($recipeYield)) {
        $servings = (int) $recipeYield;
        return $servings > 0 ? $servings : null;
    }

    if (is_array($recipeYield)) {
        foreach ($recipeYield as $item) {
            $parsed = parseRecipeYieldToServings($item);
            if ($parsed !== null) {
                return $parsed;
            }
        }
        return null;
    }

    $text = trim((string) $recipeYield);
    if ($text === '') {
        return null;
    }

    if (preg_match('/(\d{1,2})/', $text, $matches) === 1) {
        $servings = (int) $matches[1];
        return $servings > 0 ? $servings : null;
    }

    return null;
}

function extractRecipeInstructionsAsSteps($instructions): array
{
    if (is_string($instructions)) {
        $parts = preg_split('/\r\n|\r|\n/', $instructions);
        return array_values(array_filter(array_map('trim', $parts ?: []), static fn($step): bool => $step !== ''));
    }

    if (!is_array($instructions)) {
        return [];
    }

    $steps = [];
    foreach ($instructions as $entry) {
        if (is_string($entry)) {
            $step = trim($entry);
            if ($step !== '') {
                $steps[] = $step;
            }
            continue;
        }

        if (is_array($entry)) {
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '') {
                $steps[] = $text;
                continue;
            }

            if (isset($entry['itemListElement']) && is_array($entry['itemListElement'])) {
                foreach (extractRecipeInstructionsAsSteps($entry['itemListElement']) as $nestedStep) {
                    $steps[] = $nestedStep;
                }
            }
        }
    }

    return array_values(array_filter(array_map('trim', $steps), static fn($step): bool => $step !== ''));
}

function normalizeRecipeIngredientText(string $value): string
{
    $text = mb_strtolower(trim($value));
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string) $text);
}

function computeDanishRecipeSignal(string $title, string $description, array $ingredients, array $steps): array
{
    $blob = mb_strtolower(trim($title . ' ' . $description . ' ' . implode(' ', $ingredients) . ' ' . implode(' ', $steps)));

    $danishStopWords = [
        ' og ', ' med ', ' til ', ' i ', ' pa ', ' af ', ' det ', ' den ', ' de ', ' en ', ' et ',
        ' minutter', ' time', ' ovn', ' steg', ' bland', ' server', ' varm',
    ];
    $danishFoodWords = [
        'log', 'hvidlog', 'gulerod', 'kartoffel', 'hakket', 'flode', 'maelk', 'smor', 'ost', 'kylling',
        'svinekod', 'oksekod', 'fars', 'salt', 'peber', 'persille', 'dild', 'pisk', 'bag',
    ];

    $hits = 0;
    foreach ($danishStopWords as $word) {
        if (str_contains($blob, $word)) {
            $hits++;
        }
    }

    $foodHits = 0;
    foreach ($danishFoodWords as $word) {
        if (str_contains($blob, $word)) {
            $foodHits++;
        }
    }

    $specialChars = preg_match_all('/[aeo]/u', strtr($blob, ['æ' => 'a', 'ø' => 'o', 'å' => 'a']), $matches);
    $score = min(1.0, (($hits * 0.06) + ($foodHits * 0.08) + (min(6, $specialChars) * 0.01)));

    return [
        'score' => round($score, 3),
        'is_danish' => $score >= 0.35,
    ];
}

function fetchRecipeHtmlFromUrl(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml',
            'User-Agent: MadRecipeImporter/0.1 (+https://example.local)',
        ],
    ]);

    $html = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($html) || $html === '' || $httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Kunne ikke hente opskriftssiden (HTTP ' . $httpCode . ')');
    }

    return $html;
}

function fetchRecipeJsonLdFromUrl(string $url): array
{
    $html = fetchRecipeHtmlFromUrl($url);

    // Try Recipe JSON-LD first
    if (preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
        $jsonBlocks = $matches[1] ?? [];
        foreach ($jsonBlocks as $block) {
            $decoded = json_decode(trim((string) $block), true);
            if (!is_array($decoded)) {
                continue;
            }

            $candidates = [];
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                $candidates = $decoded['@graph'];
            } elseif (array_is_list($decoded)) {
                $candidates = $decoded;
            } else {
                $candidates = [$decoded];
            }

            foreach ($candidates as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                $type = $candidate['@type'] ?? null;
                if (is_array($type)) {
                    $hasRecipe = in_array('Recipe', $type, true);
                } else {
                    $hasRecipe = (string) $type === 'Recipe';
                }
                if ($hasRecipe) {
                    return $candidate;
                }
            }
        }
    }

    // Try HTML microdata extraction (e.g., itemprop recipeIngredient/recipeInstructions)
    $microData = extractRecipeFromMicrodataHtml($html);
    if ($microData !== null) {
        return $microData;
    }

    // Try AI extraction directly from raw HTML (much more reliable than regex)
    $aiResult = extractRecipeWithAiFromHtml($html);
    if ($aiResult !== null) {
        return $aiResult;
    }

    // Final fallback: basic regex HTML parsing
    return parseRecipeFromHtml($html);
}

function stripHtmlForAi(string $html): string
{
    // Remove non-content blocks
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<(nav|header|footer|aside|form|dialog|noscript)\b[^>]*>.*?<\/\1>/is', '', $html);

    // Find the recipe section in raw HTML BEFORE stripping tags.
    // Look for the last occurrence of common Danish recipe anchors in HTML text nodes.
    $anchors = ['Ingredienser', 'Fremgangsmåde'];
    $bestPos = false;
    foreach ($anchors as $anchor) {
        $found = mb_stripos($html, $anchor);
        if ($found !== false) {
            $bestPos = max(0, $found - 1000);
            break;
        }
    }

    if ($bestPos !== false) {
        $html = mb_substr($html, $bestPos, 40000);
    }

    $html = strip_tags($html);
    $html = preg_replace('/[ \t]+/', ' ', $html);
    $html = preg_replace('/\n{3,}/', "\n\n", $html);
    return mb_substr(trim($html), 0, 20000);
}

function extractRecipeFromMicrodataHtml(string $html): ?array
{
    if (!str_contains($html, 'itemprop="recipeIngredient"') || !str_contains($html, 'itemprop="recipeInstructions"')) {
        return null;
    }

    $title = '';
    if (preg_match('/<h2[^>]*itemprop="name"[^>]*>(.*?)<\/h2>/is', $html, $m) === 1) {
        $title = trim(html_entity_decode(strip_tags((string) $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($title === '' && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m) === 1) {
        $title = trim(html_entity_decode(strip_tags((string) $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($title === '') {
        return null;
    }

    $description = '';
    if (preg_match('/<meta\s+property="og:description"\s+content="([^"]+)"/i', $html, $m) === 1) {
        $description = trim(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $ingredients = [];
    if (preg_match_all('/<li[^>]*itemprop="recipeIngredient"[^>]*>(.*?)<\/li>/is', $html, $matches)) {
        foreach ($matches[1] as $item) {
            $line = trim(html_entity_decode(strip_tags((string) $item), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $line = preg_replace('/\s+/u', ' ', $line);
            if ($line !== '') {
                $ingredients[] = $line;
            }
        }
    }

    $steps = [];
    if (preg_match('/<div[^>]*itemprop="recipeInstructions"[^>]*>(.*?)<\/div>/is', $html, $instructionsMatch) === 1) {
        $instructionsHtml = (string) $instructionsMatch[1];
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $instructionsHtml, $paragraphs)) {
            foreach ($paragraphs[1] as $paragraph) {
                $text = trim(html_entity_decode(strip_tags((string) $paragraph), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $text = preg_replace('/\s+/u', ' ', $text);
                if ($text !== '') {
                    $steps[] = $text;
                }
            }
        }
    }

    if ($ingredients === [] || $steps === []) {
        return null;
    }

    $recipeYield = '4';
    if (preg_match('/<span[^>]*itemprop="recipeYield"[^>]*>(.*?)<\/span>/is', $html, $m) === 1) {
        $recipeYield = trim(html_entity_decode(strip_tags((string) $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $prepTime = 'PT15M';
    $cookTime = 'PT30M';
    $totalTime = 'PT45M';
    if (preg_match('/<span[^>]*itemprop="prepTime"[^>]*>(.*?)<\/span>/is', $html, $m) === 1) {
        $prepTime = trim((string) $m[1]);
    }
    if (preg_match('/<span[^>]*itemprop="cookTime"[^>]*>(.*?)<\/span>/is', $html, $m) === 1) {
        $cookTime = trim((string) $m[1]);
    }
    if (preg_match('/<span[^>]*itemprop="totalTime"[^>]*>(.*?)<\/span>/is', $html, $m) === 1) {
        $totalTime = trim((string) $m[1]);
    }

    return [
        'name' => $title,
        'description' => $description,
        'recipeIngredient' => $ingredients,
        'recipeInstructions' => array_map(static fn(string $step): array => ['text' => $step], $steps),
        'recipeYield' => $recipeYield,
        'prepTime' => $prepTime,
        'cookTime' => $cookTime,
        'totalTime' => $totalTime,
        'ai_cleaned' => true,
    ];
}

function extractRecipeWithAiFromHtml(string $html): ?array
{
    $apiKey = (string) (env_value('ANTHROPIC_API_KEY', '') ?? '');
    if ($apiKey === '' || strtolower((string) (env_value('AI_ENABLED', 'false') ?? 'false')) !== 'true') {
        return null;
    }

    try {
        require_once __DIR__ . '/AiHandler.php';

        $pageText = stripHtmlForAi($html);

        $prompt = "Følgende tekst er hentet fra en opskriftsside. Udtræk præcist opskriften og svar som ren JSON (ingen markdown, ingen forklaring).\n"
            . "VIGTIGT: Medtag ALLE ingredienser og ALLE trin fra selve opskriften. Undlad tips, kommentarer, navigation og andet støjindhold.\n\n"
            . $pageText . "\n\n"
            . "Svar KUN med dette JSON-objekt:\n"
            . "{\n"
            . "  \"title\": \"[Opskriftens navn]\",\n"
            . "  \"description\": \"[Kort beskrivelse]\",\n"
            . "  \"servings\": 4,\n"
            . "  \"total_minutes\": 30,\n"
            . "  \"prep_minutes\": 10,\n"
            . "  \"cook_minutes\": 20,\n"
            . "  \"ingredients\": [\"2 løg, finthakket\", \"3 fed hvidløg\"],\n"
            . "  \"steps\": [\"Sauter løgene...\", \"Tilsæt tomater...\"]\n"
            . "}";

        $result = callAnthropic(
            'Du er en kulinarisk data-specialist. Du udtrækker opskrifter fra websider præcist og svarer altid som ren JSON.',
            $prompt,
            3200
        );

        if (!$result['ok']) {
            return null;
        }

        // Strip potential markdown code fences
        $text = trim($result['text']);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $cleaned = json_decode($text, true);
        if (!is_array($cleaned) || empty($cleaned['title'])) {
            return null;
        }

        $prepMin  = (int) ($cleaned['prep_minutes'] ?? 0);
        $cookMin  = (int) ($cleaned['cook_minutes'] ?? 0);
        $totalMin = (int) ($cleaned['total_minutes'] ?? ($prepMin + $cookMin));

        return [
            'name'               => $cleaned['title'],
            'description'        => $cleaned['description'] ?? '',
            'recipeIngredient'   => array_values(array_filter((array) ($cleaned['ingredients'] ?? []))),
            'recipeInstructions' => array_map(static fn($s) => ['text' => (string) $s], array_values(array_filter((array) ($cleaned['steps'] ?? [])))),
            'recipeYield'        => (string) ($cleaned['servings'] ?? '4'),
            'prepTime'           => 'PT' . $prepMin . 'M',
            'cookTime'           => 'PT' . $cookMin . 'M',
            'totalTime'          => 'PT' . $totalMin . 'M',
            'ai_cleaned'         => true,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function parseRecipeFromHtml(string $html): array
{
    // Extract title from h1 or meta og:title
    $title = '';
    if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $m)) {
        $title = trim(strip_tags($m[1]));
    } elseif (preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $m)) {
        $title = trim($m[1]);
    } elseif (preg_match('/<title>([^<]+)<\/title>/i', $html, $m)) {
        $title = trim($m[1]);
    }

    if ($title === '') {
        throw new RuntimeException('Kunne ikke parse opskrift: ingen titel fundet');
    }

    // Extract description from meta og:description or first p tag
    $description = '';
    if (preg_match('/<meta\s+property="og:description"\s+content="([^"]+)"/i', $html, $m)) {
        $description = trim($m[1]);
    } elseif (preg_match('/<p[^>]*>([^<]+)<\/p>/i', $html, $m)) {
        $description = trim(strip_tags($m[1]));
    }

    // Extract ingredients - look for common patterns
    $ingredients = [];
    
    // Pattern 1: <li> items (common in lists)
    if (preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $html, $matches)) {
        foreach ($matches[1] as $item) {
            $cleaned = trim(strip_tags($item));
            if (strlen($cleaned) > 5 && strlen($cleaned) < 200) {
                $ingredients[] = $cleaned;
            }
        }
    }

    // Pattern 2: lines with common cooking units if li didn't work well
    if (count($ingredients) < 3) {
        $lines = explode("\n", $html);
        foreach ($lines as $line) {
            $line = trim(strip_tags($line));
            // Match lines with numbers + units (e.g., "2 cups flour", "500g butter")
            if (preg_match('/^\d+[\.,\s]+(gram|g|ml|cup|spoon|tsk|tbsp|liter|l|stk|stk\.|bdt|dl|pint|oz)/i', $line)) {
                if (strlen($line) > 5 && strlen($line) < 200) {
                    $ingredients[] = $line;
                }
            }
        }
    }

    if (empty($ingredients)) {
        $ingredients = ['Ingredienser kunne ikke parses fra siden'];
    }

    // Extract instructions - numbered steps or paragraphs
    $steps = [];
    if (preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $html, $matches)) {
        foreach ($matches[1] as $item) {
            $cleaned = trim(strip_tags($item));
            if (strlen($cleaned) > 10 && strlen($cleaned) < 500) {
                $steps[] = $cleaned;
            }
        }
    }

    if (empty($steps)) {
        $steps = ['Fremgangsmåde kunne ikke parses fra siden'];
    }

    return [
        'name' => $title,
        'description' => $description,
        'recipeIngredient' => array_slice($ingredients, 0, 20),
        'recipeInstructions' => [
            ['text' => implode(' ', array_slice($steps, 0, 10))]
        ],
        'recipeYield' => '4',
        'prepTime' => 'PT15M',
        'cookTime' => 'PT30M',
        'totalTime' => 'PT45M',
    ];
}

function cleanRecipeWithAi(array $rawRecipe): array
{
    // Skip AI cleanup if not enabled or no API key
    $apiKey = (string) (env_value('ANTHROPIC_API_KEY', '') ?? '');
    if ($apiKey === '' || strtolower((string) (env_value('AI_ENABLED', 'false') ?? 'false')) !== 'true') {
        return $rawRecipe;
    }

    try {
        require_once __DIR__ . '/AiHandler.php';

        $ingredientsList = implode("\n", array_slice($rawRecipe['recipeIngredient'] ?? [], 0, 15));
        $instructionList = is_array($rawRecipe['recipeInstructions'] ?? null) 
            ? implode("\n", array_map(fn($s) => (string) ($s['text'] ?? $s), $rawRecipe['recipeInstructions']))
            : (string) ($rawRecipe['recipeInstructions'] ?? '');

        $prompt = "Du er en kulinarisk assistent. Jeg har parset følgende rådata fra en opskrift. Strukture og forbedre den. Output JSON:\n\n"
            . "INGREDIENSER:\n" . $ingredientsList . "\n\n"
            . "FREMGANGSMÅDE:\n" . $instructionList . "\n\n"
            . "OPSKRIFT-DETALJER:\n"
            . "Titel: " . ($rawRecipe['name'] ?? 'Ukendt') . "\n"
            . "Portioner: " . ($rawRecipe['recipeYield'] ?? '4') . "\n"
            . "Beskrivelse: " . ($rawRecipe['description'] ?? '') . "\n\n"
            . "Svar som ren JSON (ingen markdown):\n"
            . "{\n"
            . "  \"title\": \"[Opskrift titel]\",\n"
            . "  \"description\": \"[Kort beskrivelse]\",\n"
            . "  \"servings\": [antal portioner som number],\n"
            . "  \"prep_minutes\": [forberedelse i minutter],\n"
            . "  \"cook_minutes\": [kogning i minutter],\n"
            . "  \"ingredients\": [\n"
            . "    {\"name\": \"ingredient\", \"quantity\": \"amount\", \"unit\": \"g/ml/stk\"}\n"
            . "  ],\n"
            . "  \"steps\": [\"step 1\", \"step 2\", ...],\n"
            . "  \"tags\": [\"vegetarisk\", \"hurtig\", ...]\n"
            . "}";

        $result = callAnthropic(
            'Du er en kulinarisk data-specialist som strukturerer og forbedrer opskrifts-data. Du svarer altid som ren JSON.',
            $prompt,
            2400
        );

        if (!$result['ok']) {
            return $rawRecipe;
        }

        $cleaned = json_decode($result['text'], true);
        if (!is_array($cleaned)) {
            return $rawRecipe;
        }

        // Map cleaned data back to our schema
        return [
            'name' => $cleaned['title'] ?? $rawRecipe['name'] ?? 'Opskrift',
            'description' => $cleaned['description'] ?? $rawRecipe['description'] ?? '',
            'recipeIngredient' => array_map(
                static fn($ing) => is_array($ing) 
                    ? (($ing['quantity'] ?? '') ? $ing['quantity'] . ' ' : '') . ($ing['unit'] ?? '') . ' ' . ($ing['name'] ?? '')
                    : $ing,
                $cleaned['ingredients'] ?? []
            ),
            'recipeInstructions' => array_map(
                static fn($step) => ['text' => $step],
                $cleaned['steps'] ?? []
            ),
            'recipeYield' => (string) ($cleaned['servings'] ?? '4'),
            'prepTime' => 'PT' . (int) ($cleaned['prep_minutes'] ?? 15) . 'M',
            'cookTime' => 'PT' . (int) ($cleaned['cook_minutes'] ?? 30) . 'M',
            'totalTime' => 'PT' . ((int) ($cleaned['prep_minutes'] ?? 15) + (int) ($cleaned['cook_minutes'] ?? 30)) . 'M',
            'ai_cleaned' => true,
        ];
    } catch (Throwable $e) {
        // If AI fails, just return raw recipe
        return $rawRecipe;
    }
}

function ensureHouseholdInventoryBasisColumn(PDO $pdo): void
{
    static $attempted = false;

    if ($attempted) {
        return;
    }
    $attempted = true;

    try {
        $pdo->exec('ALTER TABLE household_inventory ADD COLUMN IF NOT EXISTS is_basis TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE household_inventory ADD COLUMN is_basis TINYINT(1) NOT NULL DEFAULT 0');
        } catch (Throwable $inner) {
            // Ignore: environments without ALTER permissions can still operate without basis toggle persistence.
        }
    }
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
    ensureHouseholdInventoryBasisColumn($pdo);
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

        $inventorySelectCols = householdInventoryHasColumn($pdo, 'is_basis')
            ? 'id, quantity, minimum_quantity, is_basis'
            : 'id, quantity, minimum_quantity, 0 AS is_basis';
        $stmt = $pdo->prepare(
            'SELECT ' . $inventorySelectCols . '
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
            $isBasis = (int) ($inventory['is_basis'] ?? 0) === 1;
            if ($isBasis && $movementType === 'out' && ($newQuantity <= 0 || ($minimumQuantity > 0 && $newQuantity <= ($minimumQuantity + 0.0001)))) {
                $autoAddedToShoppingList = addProductToShoppingListIfMissing($pdo, $householdId, $productId, $resolvedProductName);
            } elseif ($isBasis && $movementType === 'in' && $minimumQuantity > 0 && $newQuantity >= ($minimumQuantity - 0.0001)) {
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
    ensureHouseholdInventoryBasisColumn($pdo);
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);
    $locationId = isset($_GET['location_id']) ? (int) $_GET['location_id'] : null;
    $includeZero = !empty($_GET['include_zero']) ? 1 : 0;

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
        $inventoryBasisSelect = householdInventoryHasColumn($pdo, 'is_basis')
            ? 'hi.is_basis'
            : '0 AS is_basis';

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
                ' . $inventoryBasisSelect . ',
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
                             AND (? = 1 OR ROUND(hi.quantity, 2) > 0)
               AND (? IS NULL OR hi.location_id = ?)
             ORDER BY p.name ASC'
        );
                $stmt->execute([$householdId, $includeZero, $locationId, $locationId]);
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
                $today = new DateTimeImmutable('today');
                $todayStr = $today->format('Y-m-d');
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
                                             AND (valid_from IS NULL OR valid_from <= ?)
                                             AND valid_to IS NOT NULL
                                             AND valid_to >= ?
                     GROUP BY product_id
                 ) best ON best.product_id = so.product_id AND best.min_price = so.price
                 WHERE so.title = "Tilbud"
                    AND (so.valid_from IS NULL OR so.valid_from <= ?)
                                     AND so.valid_to IS NOT NULL
                                     AND so.valid_to >= ?
                 ORDER BY so.product_id ASC, so.valid_to ASC, so.id DESC'
            );
                                                $stmt->execute(array_merge($productIds, [$todayStr, $todayStr, $todayStr, $todayStr]));

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
    $today = new DateTimeImmutable('today');
    $todayStr = $today->format('Y-m-d');

    try {
                $whereClause = ' WHERE so.title LIKE "Tilbud:%"
             AND (so.valid_from IS NULL OR so.valid_from <= ?)
             AND so.valid_to IS NOT NULL
             AND so.valid_to >= ?';

        $whereParams = [$todayStr, $todayStr];
        if ($storeFilter !== '') {
            $whereClause .= ' AND so.store_name = ?';
            $whereParams[] = $storeFilter;
        }

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
             LEFT JOIN products p ON p.id = so.product_id' . $whereClause;

        $query .= ' ORDER BY so.created_at DESC, so.id DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($whereParams);

        $rows = $stmt->fetchAll() ?: [];
        $seenKeys = [];
        $dedupedRows = [];
        foreach ($rows as $row) {
            $storeKey = mb_strtolower(trim((string) ($row['store_name'] ?? '')));
            $titleKey = mb_strtolower(trim((string) ($row['title'] ?? '')));
            $priceKey = isset($row['price']) && $row['price'] !== null
                ? number_format((float) $row['price'], 4, '.', '')
                : 'null';
            $validFromKey = $row['valid_from'] !== null ? (string) $row['valid_from'] : '';
            $validToKey = $row['valid_to'] !== null ? (string) $row['valid_to'] : '';
            $dedupeKey = $storeKey . '|' . $titleKey . '|' . $priceKey . '|' . $validFromKey . '|' . $validToKey;

            if (isset($seenKeys[$dedupeKey])) {
                continue;
            }
            $seenKeys[$dedupeKey] = true;
            $dedupedRows[] = $row;
        }

        $rowsToItems = static function (array $row): array {
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
        };

        $allItems = array_map($rowsToItems, $dedupedRows);
        $items = array_slice($allItems, 0, $limit);

        $normalizeStoreLabel = static function (string $value): string {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 'Ukendt';
            }

            $normalized = strtolower(preg_replace('/\s+/', '', $trimmed));
            if ($normalized === 'netto') {
                return 'Netto';
            }
            if ($normalized === 'kvickly') {
                return 'Kvickly';
            }
            if ($normalized === '365discount' || $normalized === '365') {
                return '365discount';
            }

            return $trimmed;
        };

        $total = count($allItems);
        $matched = count(array_filter($allItems, static fn(array $item): bool => !empty($item['is_catalog_matched'])));
        $latestScrapeAt = null;
        foreach ($allItems as $item) {
            $createdAt = trim((string) ($item['created_at'] ?? ''));
            if ($createdAt !== '' && ($latestScrapeAt === null || $createdAt > $latestScrapeAt)) {
                $latestScrapeAt = $createdAt;
            }
        }

        $byStore = [];
        foreach ($allItems as $item) {
            $store = $normalizeStoreLabel((string) ($item['store_name'] ?? ''));
            $byStore[$store] = (int) ($byStore[$store] ?? 0) + 1;
        }
        ksort($byStore, SORT_NATURAL | SORT_FLAG_CASE);

        response(200, [
            'status' => 'ok',
            'items' => $items,
            'summary' => [
                'total' => $total,
                'matched' => $matched,
                'unmatched' => max(0, $total - $matched),
                'returned' => count($items),
                'by_store' => $byStore,
                'latest_scrape_at' => $latestScrapeAt,
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
                (
                    SELECT so.title
                    FROM store_offers so
                    WHERE LOWER(TRIM(so.title)) = LOWER(TRIM(si.product_name))
                      AND (
                          si.preferred_store IS NULL
                          OR TRIM(si.preferred_store) = ""
                          OR LOWER(TRIM(so.store_name)) = LOWER(TRIM(si.preferred_store))
                      )
                    ORDER BY so.created_at DESC, so.id DESC
                    LIMIT 1
                ) AS fallback_offer_title,
                (
                    SELECT so.store_name
                    FROM store_offers so
                    WHERE LOWER(TRIM(so.title)) = LOWER(TRIM(si.product_name))
                      AND (
                          si.preferred_store IS NULL
                          OR TRIM(si.preferred_store) = ""
                          OR LOWER(TRIM(so.store_name)) = LOWER(TRIM(si.preferred_store))
                      )
                    ORDER BY so.created_at DESC, so.id DESC
                    LIMIT 1
                ) AS fallback_offer_store,
                                (
                                        SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                                        FROM household_inventory hi_basis
                                        WHERE hi_basis.household_id = sl.household_id
                                            AND hi_basis.product_id = si.product_id
                                            AND hi_basis.is_basis = 1
                                ) AS is_basis,
                ' . $fallbackOfferIdSelect . '
             FROM shopping_list_items si
             LEFT JOIN products p ON p.id = si.product_id
                         INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
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
            $storedOfferValidTo = ($row['offer_valid_until'] !== null) ? (string) $row['offer_valid_until'] : null;
            $exactFallbackPrice = $row['fallback_offer_price'] !== null ? (float) $row['fallback_offer_price'] : null;
            $fallbackOfferValidTo = ($row['fallback_offer_valid_to'] !== null) ? (string) $row['fallback_offer_valid_to'] : null;
            $fallbackOfferTitle = trim((string) ($row['fallback_offer_title'] ?? ''));
            $fallbackOfferStore = trim((string) ($row['fallback_offer_store'] ?? ''));

            if (!isOfferValidInCurrentWeek($storedOfferValidTo)) {
                $storedOfferId = null;
                $storedOfferPrice = null;
                $storedOfferValidTo = null;
            }
            if (!isOfferValidInCurrentWeek($fallbackOfferValidTo)) {
                $exactFallbackPrice = null;
                $fallbackOfferValidTo = null;
                $fallbackOfferTitle = '';
                $fallbackOfferStore = '';
            }

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
                'offer_title' => $fallbackOfferTitle,
                'offer_price' => ($storedOfferPrice !== null)
                    ? $storedOfferPrice
                    : $exactFallbackPrice,
                'offer_valid_to' => ($storedOfferPrice !== null)
                    ? $storedOfferValidTo
                    : $fallbackOfferValidTo,
                'offer_store' => $fallbackOfferStore,
                'offer_source' => $offerSource,
                'offer_match_score' => null,
                'is_basis' => !empty($row['is_basis']),
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
            $items[$index]['offer_title'] = (string) ($fallback['offer_title'] ?? '');
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
        'cremefraiche' => true,
        'fraiche' => true,
        'smoer' => true,
        'floe' => true,
        'flode' => true,
        'marmelade' => true,
        'kakao' => true,
        'juice' => true,
        'cola' => true,
        'sodavand' => true,
        'vand' => true,
        'mineralvand' => true,
        'energidrik' => true,
        'kaffe' => true,
        'instantkaffe' => true,
        'te' => true,
        'chips' => true,
        'snack' => true,
        'snacks' => true,
        'noedder' => true,
        'nodder' => true,
        'slik' => true,
        'chokolade' => true,
        'is' => true,
        'oel' => true,
        'ol' => true,
        'beer' => true,
        'tuborg' => true,
        'carlsberg' => true,
        'heineken' => true,
        'royal' => true,
        'ale' => true,
        'ipa' => true,
        'lager' => true,
        'pilsner' => true,
        'classic' => true,
        'ble' => true,
        'bleer' => true,
        'toiletpapir' => true,
        'koekkenrulle' => true,
        'kokkenrulle' => true,
        'opvask' => true,
        'opvasketabs' => true,
        'opvaskemiddel' => true,
        'vaskemiddel' => true,
        'vaskepulver' => true,
        'skyllemiddel' => true,
        'rengoering' => true,
        'rengoring' => true,
        'shampoo' => true,
        'balsam' => true,
        'tandpasta' => true,
        'saebe' => true,
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
        'hvidloeg' => true,
        'roedloeg' => true,
        'foraarsloeg' => true,
        'loeg' => true,
        'banan' => true,
        'aeble' => true,
        'agurk' => true,
        'avocado' => true,
        'avocadoer' => true,
        'avokado' => true,
        'avokadoer' => true,
        'friture' => true,
        'olie' => true,
        'rapsolie' => true,
        'solsikkeolie' => true,
    ];

    static $families = [
        'oel' => ['ol', 'beer', 'tuborg', 'carlsberg', 'heineken', 'royal', 'ale', 'ipa', 'lager', 'pilsner', 'classic'],
        'ol' => ['oel', 'beer', 'tuborg', 'carlsberg', 'heineken', 'royal', 'ale', 'ipa', 'lager', 'pilsner', 'classic'],
        'beer' => ['oel', 'ol', 'tuborg', 'carlsberg', 'heineken', 'royal', 'ale', 'ipa', 'lager', 'pilsner', 'classic'],
        'tuborg' => ['oel', 'ol', 'beer', 'lager', 'pilsner', 'classic'],
        'carlsberg' => ['oel', 'ol', 'beer', 'lager', 'pilsner', 'classic'],
        'heineken' => ['oel', 'ol', 'beer', 'lager', 'pilsner'],
        'royal' => ['oel', 'ol', 'beer', 'lager', 'pilsner'],
        'pilsner' => ['oel', 'ol', 'beer', 'lager', 'tuborg', 'carlsberg'],
        'lager' => ['oel', 'ol', 'beer', 'pilsner', 'tuborg', 'carlsberg'],
        'classic' => ['oel', 'ol', 'beer', 'tuborg', 'carlsberg'],
        'sodavand' => ['cola', 'faxe', 'kondi', 'pepsi', 'coca', 'sprite', 'fanta', 'cocio', 'energidrik'],
        'cola' => ['sodavand', 'pepsi', 'coca', 'cocacola'],
        'vand' => ['mineralvand', 'kildevand', 'danskvand'],
        'kaffe' => ['instantkaffe', 'nescafe', 'merrild', 'bki', 'lavazza'],
        'te' => ['lipton', 'pickwick', 'earl', 'green'],
        'cremefraiche' => ['fraiche', 'creme'],
        'fraiche' => ['cremefraiche', 'creme'],
        'chips' => ['snack', 'snacks', 'lays', 'kims', 'pringles', 'noedder', 'nodder'],
        'snacks' => ['chips', 'kims', 'lays', 'pringles', 'noedder', 'nodder'],
        'noedder' => ['snack', 'snacks', 'chips'],
        'nodder' => ['snack', 'snacks', 'chips'],
        'slik' => ['chokolade', 'haribo', 'matador', 'vingummi', 'lakrids'],
        'chokolade' => ['slik', 'marabou', 'ritter', 'kitkat', 'twix'],
        'opvask' => ['opvasketabs', 'opvaskemiddel', 'fairy', 'neophos', 'finish'],
        'opvasketabs' => ['opvask', 'neophos', 'finish', 'fairy'],
        'opvaskemiddel' => ['opvask', 'fairy', 'neutral'],
        'vaskemiddel' => ['vaskepulver', 'omo', 'biotex', 'ariel', 'neutral', 'skyllemiddel'],
        'vaskepulver' => ['vaskemiddel', 'omo', 'biotex', 'ariel'],
        'skyllemiddel' => ['vaskemiddel', 'comfort', 'neutral'],
        'rengoering' => ['ajax', 'cif', 'klorin', 'spray'],
        'rengoring' => ['ajax', 'cif', 'klorin', 'spray'],
        'toiletpapir' => ['koekkenrulle', 'kokkenrulle', 'lambi', 'lotus'],
        'koekkenrulle' => ['toiletpapir', 'kokkenrulle', 'lambi', 'lotus'],
        'kokkenrulle' => ['toiletpapir', 'koekkenrulle', 'lambi', 'lotus'],
        'bleer' => ['ble', 'libero', 'pampers'],
        'ble' => ['bleer', 'libero', 'pampers'],
        'shampoo' => ['balsam', 'head', 'shoulders', 'pantene'],
        'balsam' => ['shampoo', 'pantene', 'elvital'],
        'tandpasta' => ['colgate', 'zendium', 'sensodyne'],
        'avokado' => ['avocado'],
        'avocado' => ['avokado'],
        'avocadoer' => ['avocado', 'avokado', 'avokadoer'],
        'avokadoer' => ['avocado', 'avocadoer', 'avokado'],
        'friture' => ['olie', 'rapsolie', 'solsikkeolie'],
        'olie' => ['friture', 'rapsolie', 'solsikkeolie', 'olivenolie'],
        'loeg' => ['hvidloeg', 'roedloeg', 'foraarsloeg'],
    ];

    $tokens = shoppingMatchTokens($value);
    $hits = [];
    foreach ($tokens as $token) {
        if (isset($known[$token])) {
            $hits[$token] = true;

            if (isset($families[$token])) {
                foreach ($families[$token] as $familyToken) {
                    $hits[$familyToken] = true;
                }
            }
        }
    }
    return array_keys($hits);
}

function isOfferValidInCurrentWeek(?string $validTo): bool
{
    if ($validTo === null) {
        return false;
    }

    $value = trim((string) $validTo);
    if ($value === '') {
        return false;
    }

    $toTs = strtotime($value);
    if ($toTs === false) {
        return false;
    }

    $todayTs = strtotime(date('Y-m-d'));
    return $toTs >= $todayTs;
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
    $today = new DateTimeImmutable('today');

    $sql = 'SELECT id, store_name, title, price, valid_to, created_at
            FROM store_offers
                        WHERE (valid_from IS NULL OR valid_from <= ?)
                            AND valid_to IS NOT NULL
              AND valid_to >= ?';
    $params = [$today->format('Y-m-d'), $today->format('Y-m-d')];

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
        $shoppingKeywords = knownFoodKeywords($productName);
        $offerKeywords = knownFoodKeywords($title);
        $keywordIntersect = ($shoppingKeywords !== [] && $offerKeywords !== [])
            ? array_values(array_intersect($shoppingKeywords, $offerKeywords))
            : [];

        if ($keywordIntersect !== []) {
            $score = max($score, 62.0 + min((count($keywordIntersect) - 1) * 6.0, 18.0));
        }

        if ($score < 58.0) {
            continue;
        }

        $strongOverlap = hasStrongTokenOverlap($productName, $title);
        if (!$strongOverlap && $keywordIntersect === [] && $score < 92.0) {
            continue;
        }

        if ($shoppingKeywords !== [] && $offerKeywords !== []) {
            if ($keywordIntersect === [] && $score < 99.0) {
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

function resolveOfferSuggestionsForShoppingItem(PDO $pdo, string $productName, int $limit = 8): array
{
    $normalizedName = normalizeShoppingMatchText($productName);
    if ($normalizedName === '') {
        return [];
    }

    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 20) {
        $limit = 20;
    }

    $today = new DateTimeImmutable('today');
    $stmt = $pdo->prepare(
        'SELECT id, store_name, title, price, valid_to, created_at
         FROM store_offers
                                 WHERE (valid_from IS NULL OR valid_from <= ?)
                                     AND valid_to IS NOT NULL
                     AND valid_to >= ?
         ORDER BY created_at DESC, id DESC
         LIMIT 800'
    );
    $stmt->execute([$today->format('Y-m-d'), $today->format('Y-m-d')]);
    $offers = $stmt->fetchAll() ?: [];

    $candidates = [];
    foreach ($offers as $offer) {
        $title = (string) ($offer['title'] ?? '');
        $score = scoreShoppingTitleMatch($productName, $title);
        $shoppingKeywords = knownFoodKeywords($productName);
        $offerKeywords = knownFoodKeywords($title);
        $keywordIntersect = ($shoppingKeywords !== [] && $offerKeywords !== [])
            ? array_values(array_intersect($shoppingKeywords, $offerKeywords))
            : [];

        if ($keywordIntersect !== []) {
            $score = max($score, 62.0 + min((count($keywordIntersect) - 1) * 6.0, 18.0));
        }

        if ($score < 58.0) {
            continue;
        }

        $strongOverlap = hasStrongTokenOverlap($productName, $title);
        if (!$strongOverlap && $keywordIntersect === [] && $score < 92.0) {
            continue;
        }

        if ($shoppingKeywords !== [] && $offerKeywords !== []) {
            if ($keywordIntersect === [] && $score < 99.0) {
                continue;
            }
        }

        $candidates[] = [
            'offer_id' => isset($offer['id']) ? (int) $offer['id'] : null,
            'offer_store' => (string) ($offer['store_name'] ?? ''),
            'offer_title' => $title,
            'offer_price' => isset($offer['price']) ? (float) $offer['price'] : null,
            'offer_valid_to' => $offer['valid_to'] !== null ? (string) $offer['valid_to'] : null,
            'score' => $score,
        ];
    }

    usort($candidates, static function (array $a, array $b): int {
        $scoreCmp = (float) ($b['score'] ?? 0) <=> (float) ($a['score'] ?? 0);
        if ($scoreCmp !== 0) {
            return $scoreCmp;
        }

        $aPrice = $a['offer_price'] !== null ? (float) $a['offer_price'] : 999999.0;
        $bPrice = $b['offer_price'] !== null ? (float) $b['offer_price'] : 999999.0;
        return $aPrice <=> $bPrice;
    });

    $byStore = [];
    $result = [];
    foreach ($candidates as $candidate) {
        $store = trim((string) ($candidate['offer_store'] ?? ''));
        $storeKey = mb_strtolower($store, 'UTF-8');

        // Keep the strongest candidate per store for cleaner cross-store choices.
        if ($storeKey !== '') {
            if (isset($byStore[$storeKey])) {
                continue;
            }
            $byStore[$storeKey] = true;
        }

        $result[] = $candidate;
        if (count($result) >= $limit) {
            break;
        }
    }

    return $result;
}

function handleShoppingListOfferSuggestions(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $itemId = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 6;

    if ($itemId <= 0) {
        response(400, ['error' => 'Missing or invalid item_id']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT si.id, si.product_name, p.name AS linked_product_name
             FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             LEFT JOIN products p ON p.id = si.product_id
             WHERE si.id = ?
               AND sl.household_id = ?
               AND sl.status IN ("open", "in_progress")
             LIMIT 1'
        );
        $stmt->execute([$itemId, $householdId]);
        $item = $stmt->fetch();

        if (!$item) {
            response(404, ['error' => 'Shopping list item not found']);
            return;
        }

        $productName = trim((string) ($item['linked_product_name'] ?? $item['product_name'] ?? ''));
        if ($productName === '') {
            response(200, [
                'status' => 'ok',
                'item_id' => $itemId,
                'product_name' => '',
                'suggestions' => [],
            ]);
            return;
        }

        $suggestions = resolveOfferSuggestionsForShoppingItem($pdo, $productName, $limit);

        response(200, [
            'status' => 'ok',
            'item_id' => $itemId,
            'product_name' => $productName,
            'suggestions' => $suggestions,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function buildRecipeSelectColumns(PDO $pdo): string
{
    $columns = [
        'id',
        'owner_household_id',
        'title',
        'description',
        'source_type',
        'source_reference',
        'is_shared',
        'created_at',
        'updated_at',
    ];

    foreach (['locale', 'servings', 'total_minutes', 'is_danish_verified', 'import_score'] as $optionalColumn) {
        if (recipesHasColumn($pdo, $optionalColumn)) {
            $columns[] = $optionalColumn;
        }
    }

    return implode(', ', $columns);
}

function insertRecipeRecord(PDO $pdo, int $householdId, array $recipePayload): int
{
    $columns = ['owner_household_id', 'title', 'description', 'source_type', 'source_reference', 'is_shared'];
    $values = [
        $householdId,
        trim((string) ($recipePayload['title'] ?? '')),
        trim((string) ($recipePayload['description'] ?? '')),
        (string) ($recipePayload['source_type'] ?? 'manual'),
        trim((string) ($recipePayload['source_reference'] ?? '')),
        0,
    ];

    if (recipesHasColumn($pdo, 'locale')) {
        $columns[] = 'locale';
        $values[] = (string) ($recipePayload['locale'] ?? 'da-DK');
    }
    if (recipesHasColumn($pdo, 'servings')) {
        $columns[] = 'servings';
        $values[] = isset($recipePayload['servings']) ? (int) $recipePayload['servings'] : null;
    }
    if (recipesHasColumn($pdo, 'total_minutes')) {
        $columns[] = 'total_minutes';
        $values[] = isset($recipePayload['total_minutes']) ? (int) $recipePayload['total_minutes'] : null;
    }
    if (recipesHasColumn($pdo, 'is_danish_verified')) {
        $columns[] = 'is_danish_verified';
        $values[] = !empty($recipePayload['is_danish_verified']) ? 1 : 0;
    }
    if (recipesHasColumn($pdo, 'import_score')) {
        $columns[] = 'import_score';
        $values[] = isset($recipePayload['import_score']) ? (float) $recipePayload['import_score'] : null;
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO recipes (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    return (int) $pdo->lastInsertId();
}

function insertRecipeIngredients(PDO $pdo, int $recipeId, array $ingredients): int
{
    if ($ingredients === []) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit)
         VALUES (?, ?, ?, ?)'
    );

    $count = 0;
    foreach ($ingredients as $ingredient) {
        if (is_string($ingredient)) {
            $name = trim($ingredient);
            $quantity = null;
            $unit = null;
        } else {
            $name = trim((string) ($ingredient['name'] ?? ''));
            $quantity = isset($ingredient['quantity']) && $ingredient['quantity'] !== '' ? (float) $ingredient['quantity'] : null;
            $unit = isset($ingredient['unit']) ? trim((string) $ingredient['unit']) : null;
        }

        if ($name === '') {
            continue;
        }

        $stmt->execute([$recipeId, $name, $quantity, $unit !== '' ? $unit : null]);
        $count++;
    }

    return $count;
}

function insertRecipeSteps(PDO $pdo, int $recipeId, array $steps): int
{
    if ($steps === [] || !tableExists($pdo, 'recipe_steps')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO recipe_steps (recipe_id, step_number, instruction)
         VALUES (?, ?, ?)'
    );

    $count = 0;
    $stepNumber = 1;
    foreach ($steps as $step) {
        $instruction = trim((string) $step);
        if ($instruction === '') {
            continue;
        }
        $stmt->execute([$recipeId, $stepNumber, $instruction]);
        $stepNumber++;
        $count++;
    }

    return $count;
}

function handleRecipeList(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);
    $includeDetails = isset($_GET['include']) && strtolower((string) $_GET['include']) === 'details';

    try {
        $stmt = $pdo->prepare(
            'SELECT ' . buildRecipeSelectColumns($pdo) . '
             FROM recipes
             WHERE owner_household_id = ?
             ORDER BY title ASC, id ASC'
        );
        $stmt->execute([$householdId]);
        $recipes = $stmt->fetchAll() ?: [];

        if ($includeDetails && $recipes !== []) {
            $ids = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $recipes);
            $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $ingStmt = $pdo->prepare(
                    'SELECT recipe_id, ingredient_name, quantity, unit
                     FROM recipe_ingredients
                     WHERE recipe_id IN (' . $placeholders . ')
                     ORDER BY recipe_id ASC, id ASC'
                );
                $ingStmt->execute($ids);
                $ingredientsByRecipe = [];
                foreach ($ingStmt->fetchAll() ?: [] as $row) {
                    $rid = (int) ($row['recipe_id'] ?? 0);
                    $ingredientsByRecipe[$rid][] = [
                        'name' => (string) ($row['ingredient_name'] ?? ''),
                        'quantity' => $row['quantity'] !== null ? (float) $row['quantity'] : null,
                        'unit' => $row['unit'] !== null ? (string) $row['unit'] : null,
                    ];
                }

                $stepsByRecipe = [];
                if (tableExists($pdo, 'recipe_steps')) {
                    $stepStmt = $pdo->prepare(
                        'SELECT recipe_id, step_number, instruction
                         FROM recipe_steps
                         WHERE recipe_id IN (' . $placeholders . ')
                         ORDER BY recipe_id ASC, step_number ASC'
                    );
                    $stepStmt->execute($ids);
                    foreach ($stepStmt->fetchAll() ?: [] as $row) {
                        $rid = (int) ($row['recipe_id'] ?? 0);
                        $stepsByRecipe[$rid][] = [
                            'step_number' => (int) ($row['step_number'] ?? 0),
                            'instruction' => (string) ($row['instruction'] ?? ''),
                        ];
                    }
                }

                foreach ($recipes as &$recipe) {
                    $rid = (int) ($recipe['id'] ?? 0);
                    $recipe['ingredients'] = $ingredientsByRecipe[$rid] ?? [];
                    $recipe['steps'] = $stepsByRecipe[$rid] ?? [];
                }
                unset($recipe);
            }
        }

        response(200, [
            'status' => 'ok',
            'recipes' => $recipes,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleRecipeDelete(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput() ?: [];
    $recipeId = (int) ($data['recipe_id'] ?? 0);
    if ($recipeId <= 0) {
        response(400, ['error' => 'Missing recipe_id']);
        return;
    }

    // Verify recipe belongs to this household
    $stmt = $pdo->prepare('SELECT id FROM recipes WHERE id = ? AND owner_household_id = ? LIMIT 1');
    $stmt->execute([$recipeId, $householdId]);
    if (!$stmt->fetch()) {
        response(404, ['error' => 'Recipe not found']);
        return;
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM meal_plan_days WHERE recipe_id = ?')->execute([$recipeId]);
    $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
    $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id = ?')->execute([$recipeId]);
    $pdo->prepare('DELETE FROM recipes WHERE id = ? AND owner_household_id = ?')->execute([$recipeId, $householdId]);
    $pdo->commit();

    response(200, ['status' => 'deleted', 'recipe_id' => $recipeId]);
}

function handleRecipeCreate(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput() ?: [];
    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $ingredients = is_array($data['ingredients'] ?? null) ? $data['ingredients'] : [];
    $steps = is_array($data['steps'] ?? null) ? $data['steps'] : [];

    if ($title === '') {
        response(400, ['error' => 'Missing title']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $recipeId = insertRecipeRecord($pdo, $householdId, [
            'title' => $title,
            'description' => $description,
            'source_type' => 'manual',
            'source_reference' => null,
            'locale' => 'da-DK',
            'servings' => isset($data['servings']) ? (int) $data['servings'] : null,
            'total_minutes' => isset($data['total_minutes']) ? (int) $data['total_minutes'] : null,
            'is_danish_verified' => true,
            'import_score' => 1.0,
        ]);

        $ingredientCount = insertRecipeIngredients($pdo, $recipeId, $ingredients);
        $stepCount = insertRecipeSteps($pdo, $recipeId, $steps);

        $pdo->commit();

        response(201, [
            'status' => 'ok',
            'recipe_id' => $recipeId,
            'ingredients_added' => $ingredientCount,
            'steps_added' => $stepCount,
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

function handleRecipeExtractUrl(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput() ?: [];
    $url = trim((string) ($data['url'] ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        response(400, ['error' => 'Missing or invalid url']);
        return;
    }

    try {
        $recipeJson = fetchRecipeJsonLdFromUrl($url);
        if (empty($recipeJson['ai_cleaned'])) {
            $recipeJson = cleanRecipeWithAi($recipeJson);
        }

        $title = trim((string) ($recipeJson['name'] ?? ''));
        if ($title === '') {
            response(422, ['error' => 'Could not extract recipe title from URL']);
            return;
        }

        $description = trim(strip_tags((string) ($recipeJson['description'] ?? '')));
        $ingredients = is_array($recipeJson['recipeIngredient'] ?? null)
            ? array_values(array_filter(array_map(static fn($line): string => trim((string) $line), $recipeJson['recipeIngredient']), static fn(string $line): bool => $line !== ''))
            : [];

        $steps = extractRecipeInstructionsAsSteps($recipeJson['recipeInstructions'] ?? null);
        $servings = parseRecipeYieldToServings($recipeJson['recipeYield'] ?? null);

        $totalMinutes = parseIso8601DurationToMinutes((string) ($recipeJson['totalTime'] ?? ''));
        if ($totalMinutes === null) {
            $prep = parseIso8601DurationToMinutes((string) ($recipeJson['prepTime'] ?? '')) ?? 0;
            $cook = parseIso8601DurationToMinutes((string) ($recipeJson['cookTime'] ?? '')) ?? 0;
            $sum = $prep + $cook;
            $totalMinutes = $sum > 0 ? $sum : null;
        }

        response(200, [
            'status' => 'ok',
            'title' => $title,
            'description' => $description,
            'servings' => $servings,
            'total_minutes' => $totalMinutes,
            'ingredients' => $ingredients,
            'steps' => $steps,
        ]);
    } catch (RuntimeException $e) {
        response(422, [
            'error' => 'Could not parse recipe from URL',
            'message' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Recipe extraction failed',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleRecipeImportUrl(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput() ?: [];
    $url = trim((string) ($data['url'] ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        response(400, ['error' => 'Missing or invalid url']);
        return;
    }

    try {
        $recipeJson = fetchRecipeJsonLdFromUrl($url);
        if (empty($recipeJson['ai_cleaned'])) {
            $recipeJson = cleanRecipeWithAi($recipeJson);
        }

        $title = trim((string) ($recipeJson['name'] ?? ''));
        if ($title === '') {
            response(422, ['error' => 'Could not extract recipe title from URL']);
            return;
        }

        $description = trim(strip_tags((string) ($recipeJson['description'] ?? '')));
        $ingredients = is_array($recipeJson['recipeIngredient'] ?? null)
            ? array_values(array_filter(array_map(static fn($line): string => trim((string) $line), $recipeJson['recipeIngredient']), static fn(string $line): bool => $line !== ''))
            : [];

        $steps = extractRecipeInstructionsAsSteps($recipeJson['recipeInstructions'] ?? null);
        $servings = parseRecipeYieldToServings($recipeJson['recipeYield'] ?? null);

        $totalMinutes = parseIso8601DurationToMinutes((string) ($recipeJson['totalTime'] ?? ''));
        if ($totalMinutes === null) {
            $prep = parseIso8601DurationToMinutes((string) ($recipeJson['prepTime'] ?? '')) ?? 0;
            $cook = parseIso8601DurationToMinutes((string) ($recipeJson['cookTime'] ?? '')) ?? 0;
            $sum = $prep + $cook;
            $totalMinutes = $sum > 0 ? $sum : null;
        }

        $signal = computeDanishRecipeSignal($title, $description, $ingredients, $steps);

        $existingStmt = $pdo->prepare(
            'SELECT id
             FROM recipes
             WHERE owner_household_id = ? AND source_reference = ?
             LIMIT 1'
        );
        $existingStmt->execute([$householdId, $url]);
        $existingRecipe = $existingStmt->fetch();

        $pdo->beginTransaction();
        $wasUpdated = false;
        if ($existingRecipe && isset($existingRecipe['id'])) {
            $recipeId = (int) $existingRecipe['id'];
            $updateColumns = [
                'title = ?',
                'description = ?',
                'source_type = ?',
                'source_reference = ?',
            ];
            $updateValues = [$title, $description, 'external_api', $url];

            if (recipesHasColumn($pdo, 'locale')) {
                $updateColumns[] = 'locale = ?';
                $updateValues[] = 'da-DK';
            }
            if (recipesHasColumn($pdo, 'servings')) {
                $updateColumns[] = 'servings = ?';
                $updateValues[] = $servings;
            }
            if (recipesHasColumn($pdo, 'total_minutes')) {
                $updateColumns[] = 'total_minutes = ?';
                $updateValues[] = $totalMinutes;
            }
            if (recipesHasColumn($pdo, 'is_danish_verified')) {
                $updateColumns[] = 'is_danish_verified = ?';
                $updateValues[] = (bool) $signal['is_danish'] ? 1 : 0;
            }
            if (recipesHasColumn($pdo, 'import_score')) {
                $updateColumns[] = 'import_score = ?';
                $updateValues[] = (float) $signal['score'];
            }

            $updateValues[] = $recipeId;
            $updateStmt = $pdo->prepare('UPDATE recipes SET ' . implode(', ', $updateColumns) . ' WHERE id = ?');
            $updateStmt->execute($updateValues);

            $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
            if (tableExists($pdo, 'recipe_steps')) {
                $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id = ?')->execute([$recipeId]);
            }
            $wasUpdated = true;
        } else {
            $recipeId = insertRecipeRecord($pdo, $householdId, [
                'title' => $title,
                'description' => $description,
                'source_type' => 'external_api',
                'source_reference' => $url,
                'locale' => 'da-DK',
                'servings' => $servings,
                'total_minutes' => $totalMinutes,
                'is_danish_verified' => (bool) $signal['is_danish'],
                'import_score' => (float) $signal['score'],
            ]);
        }

        $ingredientCount = insertRecipeIngredients($pdo, $recipeId, $ingredients);
        $stepCount = insertRecipeSteps($pdo, $recipeId, $steps);

        $pdo->commit();

        response(201, [
            'status' => 'ok',
            'recipe_id' => $recipeId,
            'updated_existing' => $wasUpdated,
            'title' => $title,
            'danish_score' => (float) $signal['score'],
            'is_danish' => (bool) $signal['is_danish'],
            'import_status' => !empty($signal['is_danish']) ? 'accepted' : 'review_required',
            'ingredients_added' => $ingredientCount,
            'steps_added' => $stepCount,
        ]);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // User-facing errors from JSON-LD parsing should be 422
        response(422, [
            'error' => 'Could not parse recipe from URL',
            'message' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        response(500, [
            'error' => 'Import failed',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleMealPlanCurrent(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    if (!tableExists($pdo, 'meal_plans') || !tableExists($pdo, 'meal_plan_days')) {
        response(503, ['error' => 'Meal plan tables missing. Run migration first.']);
        return;
    }

    $weekStart = trim((string) ($_GET['week_start'] ?? ''));
    if ($weekStart === '') {
        $weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT mp.id, mp.week_start, mp.household_id
             FROM meal_plans mp
             WHERE mp.household_id = ? AND mp.week_start = ?
             LIMIT 1'
        );
        $stmt->execute([$householdId, $weekStart]);
        $plan = $stmt->fetch();

        if (!$plan) {
            response(200, [
                'status' => 'ok',
                'meal_plan' => null,
                'days' => [],
            ]);
            return;
        }

        $dayStmt = $pdo->prepare(
            'SELECT mpd.day_date, mpd.recipe_id, mpd.note, r.title AS recipe_title
             FROM meal_plan_days mpd
             LEFT JOIN recipes r ON r.id = mpd.recipe_id
             WHERE mpd.meal_plan_id = ?
             ORDER BY mpd.day_date ASC'
        );
        $dayStmt->execute([(int) $plan['id']]);

        response(200, [
            'status' => 'ok',
            'meal_plan' => $plan,
            'days' => $dayStmt->fetchAll() ?: [],
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleMealPlanSetDay(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    if (!tableExists($pdo, 'meal_plans') || !tableExists($pdo, 'meal_plan_days')) {
        response(503, ['error' => 'Meal plan tables missing. Run migration first.']);
        return;
    }

    $data = parseJsonInput() ?: [];
    $dayDate = trim((string) ($data['day_date'] ?? ''));
    $recipeId = (int) ($data['recipe_id'] ?? 0);
    $note = trim((string) ($data['note'] ?? ''));

    if ($dayDate === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayDate) !== 1) {
        response(400, ['error' => 'Missing or invalid day_date (YYYY-MM-DD)']);
        return;
    }
    if ($recipeId <= 0) {
        response(400, ['error' => 'Missing or invalid recipe_id']);
        return;
    }

    $weekStart = (new DateTimeImmutable($dayDate))->modify('monday this week')->format('Y-m-d');

    try {
        $pdo->beginTransaction();

        $recipeStmt = $pdo->prepare(
            'SELECT id
             FROM recipes
             WHERE id = ? AND owner_household_id = ?
             LIMIT 1'
        );
        $recipeStmt->execute([$recipeId, $householdId]);
        if (!$recipeStmt->fetch()) {
            $pdo->rollBack();
            response(404, ['error' => 'Recipe not found in this household']);
            return;
        }

        $planStmt = $pdo->prepare(
            'SELECT id
             FROM meal_plans
             WHERE household_id = ? AND week_start = ?
             LIMIT 1'
        );
        $planStmt->execute([$householdId, $weekStart]);
        $plan = $planStmt->fetch();

        if ($plan) {
            $mealPlanId = (int) $plan['id'];
        } else {
            $createPlanStmt = $pdo->prepare(
                'INSERT INTO meal_plans (household_id, week_start, created_by_user_id)
                 VALUES (?, ?, ?)'
            );
            $createPlanStmt->execute([$householdId, $weekStart, $session['user_id'] ?? null]);
            $mealPlanId = (int) $pdo->lastInsertId();
        }

        $upsertStmt = $pdo->prepare(
            'INSERT INTO meal_plan_days (meal_plan_id, day_date, recipe_id, note)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE recipe_id = VALUES(recipe_id), note = VALUES(note), updated_at = CURRENT_TIMESTAMP'
        );
        $upsertStmt->execute([$mealPlanId, $dayDate, $recipeId, $note !== '' ? $note : null]);

        $pdo->commit();

        response(200, [
            'status' => 'ok',
            'meal_plan_id' => $mealPlanId,
            'day_date' => $dayDate,
            'recipe_id' => $recipeId,
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
            $quantity = (int) ($item['quantity'] ?? 1);
            if ($quantity < 1) {
                $quantity = 1;
            }
            $offerId = (int) ($item['offerId'] ?? 0);
            $offerPrice = null;
            $offerValidUntil = null;
            $manualPriceRaw = isset($item['price']) ? trim((string) $item['price']) : '';
            if ($manualPriceRaw !== '') {
                $normalizedPrice = str_replace(',', '.', $manualPriceRaw);
                if (is_numeric($normalizedPrice)) {
                    $manualPrice = (float) $normalizedPrice;
                    if ($manualPrice >= 0) {
                        $offerPrice = $manualPrice;
                    }
                }
            }

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
                    $quantity,
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
                    $quantity,
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
    ensureHouseholdInventoryBasisColumn($pdo);
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
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT si.id, si.product_id, si.product_name, si.quantity, si.is_checked
             FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             WHERE si.id = ?
               AND sl.household_id = ?
               AND sl.status IN ("open", "in_progress")
             LIMIT 1'
        );
        $stmt->execute([$itemId, $householdId]);
        $itemRow = $stmt->fetch();

        if (!$itemRow) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            response(404, ['error' => 'Shopping list item not found']);
            return;
        }

        $wasChecked = !empty($itemRow['is_checked']) ? 1 : 0;
        $stateChanged = ($wasChecked !== $isChecked);

        if ($stateChanged) {
            $stmt = $pdo->prepare(
                'UPDATE shopping_list_items si
                 INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
                 SET si.is_checked = ?
                 WHERE si.id = ?
                   AND sl.household_id = ?
                   AND sl.status IN ("open", "in_progress")'
            );
            $stmt->execute([$isChecked, $itemId, $householdId]);
        }

        $inventoryUpdated = false;
        $inventoryQuantityAdded = 0.0;
        $inventoryQuantityDelta = 0.0;
        $inventoryProductId = isset($itemRow['product_id']) ? (int) $itemRow['product_id'] : 0;
        $itemProductName = trim((string) ($itemRow['product_name'] ?? ''));

        if ($isChecked === 1 && $wasChecked === 0 && $inventoryProductId <= 0 && $itemProductName !== '') {
            $productLookupStmt = $pdo->prepare(
                'SELECT id
                 FROM products
                 WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $productLookupStmt->execute([$itemProductName]);
            $productRow = $productLookupStmt->fetch();

            if ($productRow) {
                $inventoryProductId = (int) ($productRow['id'] ?? 0);
            } else {
                $createProductStmt = $pdo->prepare(
                    'INSERT INTO products (barcode, name)
                     VALUES (?, ?)' 
                );
                $createProductStmt->execute([null, $itemProductName]);
                $inventoryProductId = (int) $pdo->lastInsertId();
            }

            if ($inventoryProductId > 0) {
                $linkItemStmt = $pdo->prepare('UPDATE shopping_list_items SET product_id = ? WHERE id = ?');
                $linkItemStmt->execute([$inventoryProductId, $itemId]);
            }
        }

        // Sync inventory with shopping checkbox transitions:
        // unchecked -> checked: add quantity
        // checked -> unchecked: subtract quantity (floored at zero)
        $inventorySkippedReason = null;
        if ($stateChanged && $inventoryProductId > 0) {
            $quantityToAdjust = (float) ($itemRow['quantity'] ?? 1);
            if ($quantityToAdjust < 1) {
                $quantityToAdjust = 1.0;
            }

            $inventoryStmt = $pdo->prepare(
                'SELECT id, location_id, quantity, is_basis
                 FROM household_inventory
                 WHERE household_id = ?
                   AND product_id = ?
                 ORDER BY is_basis DESC, id ASC
                 LIMIT 1'
            );
            $inventoryStmt->execute([$householdId, $inventoryProductId]);
            $inventoryRow = $inventoryStmt->fetch();

            $locationId = 0;
            if ($isChecked === 1 && $wasChecked === 0 && $inventoryRow) {
                $locationId = (int) ($inventoryRow['location_id'] ?? 0);
                $newQuantity = (float) ($inventoryRow['quantity'] ?? 0) + $quantityToAdjust;

                $updateInventoryStmt = $pdo->prepare('UPDATE household_inventory SET quantity = ? WHERE id = ?');
                $updateInventoryStmt->execute([$newQuantity, (int) $inventoryRow['id']]);
                $inventoryQuantityDelta = $quantityToAdjust;
            } elseif ($isChecked === 1 && $wasChecked === 0) {
                $fallbackLocationStmt = $pdo->prepare(
                    'SELECT id
                     FROM household_locations
                     WHERE household_id = ?
                     ORDER BY id ASC
                     LIMIT 1'
                );
                $fallbackLocationStmt->execute([$householdId]);
                $fallbackLocation = $fallbackLocationStmt->fetch();

                if (!$fallbackLocation) {
                    $inventorySkippedReason = 'missing_location';
                } else {
                    $locationId = (int) ($fallbackLocation['id'] ?? 0);
                    $insertInventoryStmt = $pdo->prepare(
                        'INSERT INTO household_inventory (household_id, location_id, product_id, quantity, minimum_quantity, is_basis)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $insertInventoryStmt->execute([
                        $householdId,
                        $locationId,
                        $inventoryProductId,
                        $quantityToAdjust,
                        0,
                        0,
                    ]);
                    $inventoryQuantityDelta = $quantityToAdjust;
                }
            } elseif ($isChecked === 0 && $wasChecked === 1 && $inventoryRow) {
                $locationId = (int) ($inventoryRow['location_id'] ?? 0);
                $currentQuantity = (float) ($inventoryRow['quantity'] ?? 0);
                $newQuantity = max(0.0, $currentQuantity - $quantityToAdjust);
                $actualSubtracted = max(0.0, $currentQuantity - $newQuantity);

                if ($actualSubtracted > 0) {
                    $updateInventoryStmt = $pdo->prepare('UPDATE household_inventory SET quantity = ? WHERE id = ?');
                    $updateInventoryStmt->execute([$newQuantity, (int) $inventoryRow['id']]);
                    $inventoryQuantityDelta = -$actualSubtracted;
                } else {
                    $inventorySkippedReason = 'already_zero';
                }
            } elseif ($isChecked === 0 && $wasChecked === 1) {
                $inventorySkippedReason = 'missing_inventory';
            }

            if ($inventorySkippedReason === null && $inventoryQuantityDelta !== 0.0) {
                $movementStmt = $pdo->prepare(
                    'INSERT INTO inventory_movements (household_id, location_id, product_id, user_id, movement_type, quantity_delta, source)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $movementStmt->execute([
                    $householdId,
                    $locationId > 0 ? $locationId : null,
                    $inventoryProductId,
                    isset($session['user_id']) ? (int) $session['user_id'] : null,
                    $inventoryQuantityDelta > 0 ? 'in' : 'out',
                    abs($inventoryQuantityDelta),
                    'manual',
                ]);

                $inventoryUpdated = true;
                $inventoryQuantityAdded = $inventoryQuantityDelta;
            }
        } elseif ($stateChanged && $inventoryProductId <= 0) {
            $inventorySkippedReason = 'missing_product';
        }

        $pdo->commit();

        response(200, [
            'status' => 'ok',
            'item_id' => $itemId,
            'is_checked' => (bool) $isChecked,
            'state_changed' => (bool) $stateChanged,
            'inventory_updated' => $inventoryUpdated,
            'inventory_product_id' => $inventoryProductId > 0 ? $inventoryProductId : null,
            'inventory_quantity_added' => $inventoryQuantityAdded,
            'inventory_quantity_delta' => $inventoryQuantityDelta,
            'inventory_skipped_reason' => $inventorySkippedReason,
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

function handleShoppingListApplyOffer(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $itemId = (int) ($data['item_id'] ?? 0);
    $offerId = (int) ($data['offer_id'] ?? 0);

    if ($itemId <= 0 || $offerId <= 0) {
        response(400, ['error' => 'Missing or invalid item_id or offer_id']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $itemStmt = $pdo->prepare(
            'SELECT si.id
             FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             WHERE si.id = ?
               AND sl.household_id = ?
               AND sl.status IN ("open", "in_progress")
             LIMIT 1'
        );
        $itemStmt->execute([$itemId, $householdId]);
        if (!$itemStmt->fetch()) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            response(404, ['error' => 'Shopping list item not found']);
            return;
        }

        $offerStmt = $pdo->prepare(
            'SELECT id, store_name, title, price, valid_to
             FROM store_offers
             WHERE id = ?
             LIMIT 1'
        );
        $offerStmt->execute([$offerId]);
        $offer = $offerStmt->fetch();
        if (!$offer) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            response(404, ['error' => 'Offer not found']);
            return;
        }

        $offerStore = trim((string) ($offer['store_name'] ?? ''));
        $offerPrice = isset($offer['price']) ? (float) $offer['price'] : null;
        $offerValidTo = $offer['valid_to'] !== null ? (string) $offer['valid_to'] : null;
        $hasOfferIdColumn = shoppingListItemsHasColumn($pdo, 'offer_id');

        if ($hasOfferIdColumn) {
            $updateStmt = $pdo->prepare(
                'UPDATE shopping_list_items
                 SET preferred_store = ?,
                     offer_id = ?,
                     offer_price = ?,
                     offer_valid_until = ?
                 WHERE id = ?'
            );
            $updateStmt->execute([
                $offerStore !== '' ? $offerStore : null,
                (int) $offer['id'],
                $offerPrice,
                $offerValidTo,
                $itemId,
            ]);
        } else {
            $updateStmt = $pdo->prepare(
                'UPDATE shopping_list_items
                 SET preferred_store = ?,
                     offer_price = ?,
                     offer_valid_until = ?
                 WHERE id = ?'
            );
            $updateStmt->execute([
                $offerStore !== '' ? $offerStore : null,
                $offerPrice,
                $offerValidTo,
                $itemId,
            ]);
        }

        $pdo->commit();

        response(200, [
            'status' => 'ok',
            'item_id' => $itemId,
            'offer_id' => (int) ($offer['id'] ?? 0),
            'offer_store' => $offerStore,
            'offer_title' => (string) ($offer['title'] ?? ''),
            'offer_price' => $offerPrice,
            'offer_valid_to' => $offerValidTo,
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

function handleShoppingListSetItemBasis(PDO $pdo): void
{
    ensureHouseholdInventoryBasisColumn($pdo);
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $itemId = (int) ($data['item_id'] ?? 0);
    $isBasis = !empty($data['is_basis']) ? 1 : 0;

    if ($itemId <= 0) {
        response(400, ['error' => 'Missing or invalid item_id']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT si.id, si.product_id, si.product_name
             FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             WHERE si.id = ?
               AND sl.household_id = ?
               AND sl.status IN ("open", "in_progress")
             LIMIT 1'
        );
        $stmt->execute([$itemId, $householdId]);
        $itemRow = $stmt->fetch();

        if (!$itemRow) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            response(404, ['error' => 'Shopping list item not found']);
            return;
        }

        $productId = isset($itemRow['product_id']) ? (int) $itemRow['product_id'] : 0;
        $itemProductName = trim((string) ($itemRow['product_name'] ?? ''));

        if ($productId <= 0 && $itemProductName !== '') {
            $productLookupStmt = $pdo->prepare(
                'SELECT id
                 FROM products
                 WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $productLookupStmt->execute([$itemProductName]);
            $productRow = $productLookupStmt->fetch();

            if ($productRow) {
                $productId = (int) ($productRow['id'] ?? 0);
            } else {
                $createProductStmt = $pdo->prepare(
                    'INSERT INTO products (barcode, name)
                     VALUES (?, ?)'
                );
                $createProductStmt->execute([null, $itemProductName]);
                $productId = (int) $pdo->lastInsertId();
            }

            if ($productId > 0) {
                $linkItemStmt = $pdo->prepare('UPDATE shopping_list_items SET product_id = ? WHERE id = ?');
                $linkItemStmt->execute([$productId, $itemId]);
            }
        }

        $updatedRows = 0;
        if ($productId > 0) {
            $updateBasisStmt = $pdo->prepare(
                'UPDATE household_inventory
                 SET is_basis = ?
                 WHERE household_id = ? AND product_id = ?'
            );
            $updateBasisStmt->execute([$isBasis, $householdId, $productId]);
            $updatedRows = (int) $updateBasisStmt->rowCount();

            if ($isBasis === 1 && $updatedRows === 0) {
                $locationStmt = $pdo->prepare(
                    'SELECT id
                     FROM household_locations
                     WHERE household_id = ?
                     ORDER BY id ASC
                     LIMIT 1'
                );
                $locationStmt->execute([$householdId]);
                $locationRow = $locationStmt->fetch();
                $locationId = $locationRow ? (int) ($locationRow['id'] ?? 0) : 0;

                if ($locationId > 0) {
                    $insertInventoryStmt = $pdo->prepare(
                        'INSERT INTO household_inventory (household_id, location_id, product_id, quantity, minimum_quantity, is_basis)
                         VALUES (?, ?, ?, 0, 0, 1)
                         ON DUPLICATE KEY UPDATE is_basis = 1'
                    );
                    $insertInventoryStmt->execute([$householdId, $locationId, $productId]);
                    $updatedRows = 1;
                }
            }
        }

        $pdo->commit();

        response(200, [
            'status' => 'ok',
            'item_id' => $itemId,
            'product_id' => $productId > 0 ? $productId : null,
            'is_basis' => (bool) $isBasis,
            'updated_rows' => $updatedRows,
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

function handleShoppingListUpdateItem(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $itemId = (int) ($data['item_id'] ?? 0);
    $quantity = (int) ($data['quantity'] ?? 0);
    $preferredStore = trim((string) ($data['store'] ?? ''));

    if ($itemId <= 0) {
        response(400, ['error' => 'Missing or invalid item_id']);
        return;
    }

    if ($quantity < 1) {
        response(400, ['error' => 'Quantity must be at least 1']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT si.id, si.preferred_store
             FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             WHERE si.id = ?
               AND sl.household_id = ?
               AND sl.status IN ("open", "in_progress")
             LIMIT 1'
        );
        $stmt->execute([$itemId, $householdId]);
        $itemRow = $stmt->fetch();

        if (!$itemRow) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            response(404, ['error' => 'Shopping list item not found']);
            return;
        }

        $existingStore = trim((string) ($itemRow['preferred_store'] ?? ''));
        $storeChanged = mb_strtolower($existingStore, 'UTF-8') !== mb_strtolower($preferredStore, 'UTF-8');
        $hasOfferIdColumn = shoppingListItemsHasColumn($pdo, 'offer_id');

        if ($storeChanged) {
            if ($hasOfferIdColumn) {
                $updateStmt = $pdo->prepare(
                    'UPDATE shopping_list_items
                     SET quantity = ?,
                         preferred_store = ?,
                         offer_id = NULL,
                         offer_price = NULL,
                         offer_valid_until = NULL
                     WHERE id = ?'
                );
                $updateStmt->execute([
                    $quantity,
                    $preferredStore !== '' ? $preferredStore : null,
                    $itemId,
                ]);
            } else {
                $updateStmt = $pdo->prepare(
                    'UPDATE shopping_list_items
                     SET quantity = ?,
                         preferred_store = ?,
                         offer_price = NULL,
                         offer_valid_until = NULL
                     WHERE id = ?'
                );
                $updateStmt->execute([
                    $quantity,
                    $preferredStore !== '' ? $preferredStore : null,
                    $itemId,
                ]);
            }
        } else {
            $updateStmt = $pdo->prepare(
                'UPDATE shopping_list_items
                 SET quantity = ?,
                     preferred_store = ?
                 WHERE id = ?'
            );
            $updateStmt->execute([
                $quantity,
                $preferredStore !== '' ? $preferredStore : null,
                $itemId,
            ]);
        }

        $pdo->commit();

        response(200, [
            'status' => 'ok',
            'item_id' => $itemId,
            'quantity' => $quantity,
            'preferred_store' => $preferredStore,
            'store_changed' => $storeChanged,
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

function handleShoppingListFetchBasisLow(PDO $pdo): void
{
    ensureHouseholdInventoryBasisColumn($pdo);
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    try {
        // Find basis items where quantity = 0 OR quantity <= minimum_quantity (and not already on the open shopping list)
        $stmt = $pdo->prepare(
            'SELECT hi.product_id, p.name AS product_name
             FROM household_inventory hi
             INNER JOIN products p ON p.id = hi.product_id
             WHERE hi.household_id = ?
               AND hi.is_basis = 1
               AND (hi.quantity <= 0 OR (hi.minimum_quantity > 0 AND hi.quantity <= hi.minimum_quantity))
               AND NOT EXISTS (
                   SELECT 1
                   FROM shopping_list_items si
                   INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
                   WHERE sl.household_id = ?
                     AND sl.status IN ("open", "in_progress")
                     AND si.product_id = hi.product_id
                     AND si.is_checked = 0
               )
             ORDER BY p.name ASC'
        );
        $stmt->execute([$householdId, $householdId]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            response(200, ['status' => 'ok', 'added_count' => 0, 'message' => 'Ingen basisvarer mangler i øjeblikket']);
            return;
        }

        $shoppingListId = getOrCreateOpenShoppingListId($pdo, $householdId);
        $addedCount = 0;
        $insertStmt = $pdo->prepare(
            'INSERT INTO shopping_list_items (shopping_list_id, product_id, product_name, quantity, preferred_store)
             VALUES (?, ?, ?, 1, NULL)'
        );
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $productName = trim((string) ($row['product_name'] ?? ''));
            if ($productId <= 0 || $productName === '') {
                continue;
            }
            $insertStmt->execute([$shoppingListId, $productId, $productName]);
            $addedCount++;
        }

        response(200, [
            'status' => 'ok',
            'added_count' => $addedCount,
            'message' => $addedCount === 0 ? 'Ingen nye varer tilføjet' : "{$addedCount} basisvare(r) tilføjet til indkøbssedlen",
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleShoppingListClearChecked(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    try {
        $stmt = $pdo->prepare(
            'DELETE si FROM shopping_list_items si
             INNER JOIN shopping_lists sl ON sl.id = si.shopping_list_id
             WHERE sl.household_id = ?
               AND sl.status IN ("open", "in_progress")
               AND si.is_checked = 1'
        );
        $stmt->execute([$householdId]);

        response(200, [
            'status' => 'ok',
            'removed_count' => (int) $stmt->rowCount(),
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleInventoryDelete(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $productId = (int) ($data['product_id'] ?? 0);

    if ($productId <= 0) {
        response(400, ['error' => 'Missing or invalid product_id']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM household_inventory WHERE household_id = ? AND product_id = ?'
        );
        $stmt->execute([$householdId, $productId]);

        response(200, [
            'status' => 'ok',
            'product_id' => $productId,
            'deleted' => true,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleInventoryUpdateItem(PDO $pdo): void
{
    ensureHouseholdInventoryBasisColumn($pdo);
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $productId = (int) ($data['product_id'] ?? 0);
    $requestedLocationId = (int) ($data['location_id'] ?? 0);
    $quantity = max(0.0, (float) ($data['quantity'] ?? 0));
    $minimumQuantity = max(0.0, (float) ($data['minimum_quantity'] ?? 0));
    $isBasis = !empty($data['is_basis']) ? 1 : 0;

    if ($productId <= 0) {
        response(400, ['error' => 'Missing or invalid product_id']);
        return;
    }

    try {
        $locationId = 0;
        if ($requestedLocationId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM household_locations WHERE id = ? AND household_id = ? LIMIT 1');
            $stmt->execute([$requestedLocationId, $householdId]);
            if (!$stmt->fetch()) {
                response(403, ['error' => 'Forbidden location']);
                return;
            }
            $locationId = $requestedLocationId;
        } else {
            $stmt = $pdo->prepare('SELECT location_id FROM household_inventory WHERE household_id = ? AND product_id = ? ORDER BY id ASC LIMIT 1');
            $stmt->execute([$householdId, $productId]);
            $row = $stmt->fetch();
            if ($row && isset($row['location_id'])) {
                $locationId = (int) $row['location_id'];
            }
            if ($locationId <= 0) {
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
        }

        $stmt = $pdo->prepare(
            'SELECT id
             FROM household_inventory
             WHERE household_id = ? AND location_id = ? AND product_id = ?
             LIMIT 1'
        );
        $stmt->execute([$householdId, $locationId, $productId]);
        $existing = $stmt->fetch();

        if ($existing) {
            if (householdInventoryHasColumn($pdo, 'is_basis')) {
                $stmt = $pdo->prepare('UPDATE household_inventory SET quantity = ?, minimum_quantity = ?, is_basis = ? WHERE id = ?');
                $stmt->execute([$quantity, $minimumQuantity, $isBasis, (int) $existing['id']]);
            } else {
                $stmt = $pdo->prepare('UPDATE household_inventory SET quantity = ?, minimum_quantity = ? WHERE id = ?');
                $stmt->execute([$quantity, $minimumQuantity, (int) $existing['id']]);
            }
        } else {
            if (householdInventoryHasColumn($pdo, 'is_basis')) {
                $stmt = $pdo->prepare(
                    'INSERT INTO household_inventory (household_id, location_id, product_id, quantity, minimum_quantity, is_basis)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$householdId, $locationId, $productId, $quantity, $minimumQuantity, $isBasis]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO household_inventory (household_id, location_id, product_id, quantity, minimum_quantity)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$householdId, $locationId, $productId, $quantity, $minimumQuantity]);
            }
        }

        // Auto-add to shopping list if basis item quantity is set to 0
        $autoAddedToShopping = false;
        if ($isBasis && $quantity <= 0) {
            $nameStmt = $pdo->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');
            $nameStmt->execute([$productId]);
            $nameRow = $nameStmt->fetch();
            $productName = trim((string) ($nameRow['name'] ?? ''));
            if ($productName !== '') {
                $autoAddedToShopping = addProductToShoppingListIfMissing($pdo, $householdId, $productId, $productName);
            }
        }

        response(200, [
            'status' => 'ok',
            'product_id' => $productId,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'minimum_quantity' => $minimumQuantity,
            'is_basis' => $isBasis,
            'auto_added_to_shopping_list' => $autoAddedToShopping,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

function handleInventoryCreateItem(PDO $pdo): void
{
    ensureHouseholdInventoryBasisColumn($pdo);
    $session = requireAuthenticatedSession($pdo);
    $requestedHouseholdId = isset($_GET['household_id']) ? (int) $_GET['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $data = parseJsonInput();
    $rawBarcode = (string) ($data['barcode'] ?? '');
    $barcode = normalizeBarcode($rawBarcode);
    $providedName = trim((string) ($data['name'] ?? ''));
    $quantity = max(0.0, (float) ($data['quantity'] ?? 1));
    $minimumQuantity = max(0.0, (float) ($data['minimum_quantity'] ?? 0));
    $isBasis = !empty($data['is_basis']) ? 1 : 0;
    $requestedLocationId = (int) ($data['location_id'] ?? 0);

    if ($barcode === '') {
        response(400, ['error' => 'Missing barcode']);
        return;
    }

    try {
        $locationId = 0;
        if ($requestedLocationId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM household_locations WHERE id = ? AND household_id = ? LIMIT 1');
            $stmt->execute([$requestedLocationId, $householdId]);
            if (!$stmt->fetch()) {
                response(403, ['error' => 'Forbidden location']);
                return;
            }
            $locationId = $requestedLocationId;
        } else {
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

        $stmt = $pdo->prepare('SELECT id, name FROM products WHERE barcode = ? LIMIT 1');
        $stmt->execute([$barcode]);
        $product = $stmt->fetch();

        $createdProduct = false;
        $resolvedName = $providedName;

        if (!$product) {
            if ($resolvedName === '') {
                $offProduct = fetchOpenFoodFactsProduct($barcode);
                $resolvedName = trim((string) ($offProduct['name'] ?? ''));
            }
            if ($resolvedName === '') {
                $resolvedName = placeholderProductName($barcode);
            }

            $stmt = $pdo->prepare('INSERT INTO products (barcode, name, brand, image_url, nutrition_json) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$barcode, $resolvedName, null, null, null]);
            $productId = (int) $pdo->lastInsertId();
            $createdProduct = true;
        } else {
            $productId = (int) ($product['id'] ?? 0);
            $resolvedName = trim((string) ($product['name'] ?? ''));

            if ($providedName !== '' && $productId > 0) {
                $stmt = $pdo->prepare('UPDATE products SET name = ? WHERE id = ?');
                $stmt->execute([$providedName, $productId]);
                $resolvedName = $providedName;
            }
        }

        $stmt = $pdo->prepare(
            'SELECT id
             FROM household_inventory
             WHERE household_id = ? AND location_id = ? AND product_id = ?
             LIMIT 1'
        );
        $stmt->execute([$householdId, $locationId, $productId]);
        $inventory = $stmt->fetch();

        if ($inventory) {
            if (householdInventoryHasColumn($pdo, 'is_basis')) {
                $stmt = $pdo->prepare('UPDATE household_inventory SET quantity = ?, minimum_quantity = ?, is_basis = ? WHERE id = ?');
                $stmt->execute([$quantity, $minimumQuantity, $isBasis, (int) $inventory['id']]);
            } else {
                $stmt = $pdo->prepare('UPDATE household_inventory SET quantity = ?, minimum_quantity = ? WHERE id = ?');
                $stmt->execute([$quantity, $minimumQuantity, (int) $inventory['id']]);
            }
            $createdInventory = false;
        } else {
            if (householdInventoryHasColumn($pdo, 'is_basis')) {
                $stmt = $pdo->prepare(
                    'INSERT INTO household_inventory (household_id, location_id, product_id, quantity, minimum_quantity, is_basis)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$householdId, $locationId, $productId, $quantity, $minimumQuantity, $isBasis]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO household_inventory (household_id, location_id, product_id, quantity, minimum_quantity)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$householdId, $locationId, $productId, $quantity, $minimumQuantity]);
            }
            $createdInventory = true;
        }

        response(201, [
            'status' => 'ok',
            'barcode' => $barcode,
            'product_id' => $productId,
            'product_name' => $resolvedName,
            'created_product' => $createdProduct,
            'created_inventory' => $createdInventory,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'minimum_quantity' => $minimumQuantity,
            'is_basis' => $isBasis,
        ]);
    } catch (Throwable $e) {
        response(500, [
            'error' => 'Database error',
            'message' => $e->getMessage(),
        ]);
    }
}

