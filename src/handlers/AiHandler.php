<?php

declare(strict_types=1);

function isAiEnabled(): bool
{
    return strtolower((string) (env_value('AI_ENABLED', 'false') ?? 'false')) === 'true';
}

function callAnthropic(string $systemPrompt, string $userPrompt, ?int $maxTokensOverride = null): array
{
    $apiKey = (string) (env_value('ANTHROPIC_API_KEY', '') ?? '');
    $model = (string) (env_value('ANTHROPIC_MODEL', 'claude-3-5-haiku-latest') ?? 'claude-3-5-haiku-latest');
    $apiUrl = (string) (env_value('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages') ?? 'https://api.anthropic.com/v1/messages');
    $maxTokens = (int) (env_value('ANTHROPIC_MAX_TOKENS', '2200') ?? '2200');
    $temperature = (float) (env_value('ANTHROPIC_TEMPERATURE', '0.5') ?? '0.5');

    if ($maxTokensOverride !== null) {
        $maxTokens = $maxTokensOverride;
    }

    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'ANTHROPIC_API_KEY is missing'];
    }

    if ($maxTokens < 200 || $maxTokens > 8192) {
        $maxTokens = 2200;
    }

    if ($temperature < 0 || $temperature > 1) {
        $temperature = 0.5;
    }

    $payload = [
        'model' => $model,
        'max_tokens' => $maxTokens,
        'temperature' => $temperature,
        'system' => $systemPrompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 25,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'cURL error: ' . $curlErr];
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Invalid AI response'];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $providerError = (string) ($decoded['error']['message'] ?? 'HTTP ' . $httpCode . ' from Anthropic');
        return ['ok' => false, 'error' => $providerError];
    }

    $textChunks = [];
    $content = $decoded['content'] ?? [];
    if (is_array($content)) {
        foreach ($content as $chunk) {
            if (is_array($chunk) && ($chunk['type'] ?? '') === 'text') {
                $textChunks[] = (string) ($chunk['text'] ?? '');
            }
        }
    }

    return [
        'ok' => true,
        'text' => trim(implode("\n", $textChunks)),
        'model' => (string) ($decoded['model'] ?? $model),
    ];
}

function handleAiMealIdeas(PDO $pdo): void
{
    if (!isAiEnabled()) {
        response(503, ['error' => 'AI integration is disabled']);
        return;
    }

    $session = requireAuthenticatedSession($pdo);
    $data = parseJsonInput() ?: [];
    $requestedHouseholdId = isset($data['household_id']) ? (int) $data['household_id'] : null;
    $householdId = resolveAccessibleHouseholdId($pdo, $session, $requestedHouseholdId);

    $inventoryStmt = $pdo->prepare(
        'SELECT p.name, p.brand, hi.quantity, hi.minimum_quantity
         FROM household_inventory hi
         INNER JOIN products p ON p.id = hi.product_id
         WHERE hi.household_id = ?
         ORDER BY hi.quantity DESC, p.name ASC
         LIMIT 40'
    );
    $inventoryStmt->execute([$householdId]);
    $inventory = $inventoryStmt->fetchAll() ?: [];

    $recipeStmt = $pdo->prepare(
        'SELECT title, description
         FROM recipes
         WHERE owner_household_id = ?
         ORDER BY updated_at DESC
         LIMIT 20'
    );
    $recipeStmt->execute([$householdId]);
    $recipes = $recipeStmt->fetchAll() ?: [];

    $inventoryLines = [];
    foreach ($inventory as $item) {
        $name = (string) ($item['name'] ?? 'Ukendt vare');
        $brand = trim((string) ($item['brand'] ?? ''));
        $qty = (string) ($item['quantity'] ?? '0');
        $min = (string) ($item['minimum_quantity'] ?? '0');
        $inventoryLines[] = '- ' . $name . ($brand !== '' ? ' (' . $brand . ')' : '') . ' | beholdning: ' . $qty . ' | minimum: ' . $min;
    }

    $recipeLines = [];
    foreach ($recipes as $recipe) {
        $title = trim((string) ($recipe['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $description = trim((string) ($recipe['description'] ?? ''));
        $recipeLines[] = '- ' . $title . ($description !== '' ? ' | ' . mb_substr($description, 0, 120) : '');
    }

    $systemPrompt = 'Du er madplan-assistent for en dansk husstand. Du skal kun foreslaa realistiske, hverdagsvenlige maaltider baseret paa lager. Svar kun med gyldig JSON.';

    $userPrompt = "" .
        "Husstand ID: " . $householdId . "\n" .
        "Returner JSON med format:\n" .
        "{\n" .
        "  \"summary\": \"kort dansk opsummering\",\n" .
        "  \"meal_ideas\": [\n" .
        "    {\n" .
        "      \"name\": \"ret-navn\",\n" .
        "      \"why\": \"hvorfor den passer\",\n" .
        "      \"uses_products\": [\"...\"],\n" .
        "      \"missing_items\": [\"...\"],\n" .
        "      \"steps\": [\"kort trin\", \"kort trin\"]\n" .
        "    }\n" .
        "  ]\n" .
        "}\n\n" .
        "Maks 3 meal_ideas.\n\n" .
        "Lager:\n" . ($inventoryLines !== [] ? implode("\n", $inventoryLines) : '- Ingen varer fundet') . "\n\n" .
        "Opskrifter i husstanden:\n" . ($recipeLines !== [] ? implode("\n", $recipeLines) : '- Ingen opskrifter registreret endnu');

    $ai = callAnthropic($systemPrompt, $userPrompt);
    if (!$ai['ok']) {
        response(502, ['error' => 'AI request failed', 'details' => $ai['error'] ?? 'Unknown error']);
        return;
    }

    $rawText = (string) ($ai['text'] ?? '');
    $parsed = json_decode($rawText, true);

    if (!is_array($parsed)) {
        response(200, [
            'status' => 'ok',
            'model' => $ai['model'] ?? null,
            'household_id' => $householdId,
            'summary' => 'AI svarede, men ikke i forventet JSON-format.',
            'meal_ideas' => [],
            'raw_text' => $rawText,
        ]);
        return;
    }

    response(200, [
        'status' => 'ok',
        'model' => $ai['model'] ?? null,
        'household_id' => $householdId,
        'summary' => (string) ($parsed['summary'] ?? ''),
        'meal_ideas' => is_array($parsed['meal_ideas'] ?? null) ? $parsed['meal_ideas'] : [],
        'raw_text' => $rawText,
    ]);
}