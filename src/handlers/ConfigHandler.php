<?php

declare(strict_types=1);

/**
 * ConfigHandler – read and write selected .env settings.
 *
 * Only keys in ALLOWED_KEYS can be read or written via the API.
 * Sensitive values are masked in GET responses.
 * All endpoints require is_platform_admin.
 */

const ALLOWED_KEYS = [
    // SMS / InMobile
    'SMS_DRY_RUN',
    'INMOBILE_API_URL',
    'INMOBILE_API_TOKEN',
    'INMOBILE_SENDER',
    // AI / Anthropic
    'AI_ENABLED',
    'ANTHROPIC_API_KEY',
    'ANTHROPIC_MODEL',
    'ANTHROPIC_API_URL',
    'ANTHROPIC_MAX_TOKENS',
    'ANTHROPIC_TEMPERATURE',
];

const MASKED_KEYS = ['INMOBILE_API_TOKEN', 'ANTHROPIC_API_KEY'];

function getEnvFilePath(): string
{
    $candidates = [
        dirname(__DIR__, 2) . '/.env',
        dirname(__DIR__, 3) . '/.env',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return $candidates[0];
}

function parseEnvFile(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $values = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        if (!str_contains($trimmed, '=')) {
            continue;
        }
        [$rawKey, $rawVal] = explode('=', $trimmed, 2);
        $key = trim($rawKey);
        $val = trim($rawVal);
        // Strip surrounding quotes
        if (strlen($val) >= 2 &&
            (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        $values[$key] = $val;
    }
    return $values;
}

function writeEnvKey(string $path, string $key, string $value): bool
{
    if (!file_exists($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false;
    foreach ($lines as &$line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '#')) {
            continue;
        }
        if (!str_contains($trimmed, '=')) {
            continue;
        }
        [$rawKey] = explode('=', $trimmed, 2);
        if (trim($rawKey) === $key) {
            // Preserve inline comments after the value? We won't – keep it simple.
            $line = $key . '=' . $value;
            $found = true;
            break;
        }
    }
    unset($line);
    if (!$found) {
        // Append
        $lines[] = $key . '=' . $value;
    }
    $result = file_put_contents($path, implode("\n", $lines) . "\n");
    return $result !== false;
}

function handleConfigGet(PDO $pdo): void
{
    requirePlatformAdmin($pdo);

    $path = getEnvFilePath();
    $all = parseEnvFile($path);

    $out = [];
    foreach (ALLOWED_KEYS as $key) {
        $raw = $all[$key] ?? null;
        if ($raw !== null && in_array($key, MASKED_KEYS, true)) {
            // Show first 4 chars + mask
            $masked = strlen($raw) > 4 ? substr($raw, 0, 4) . str_repeat('*', min(12, strlen($raw) - 4)) : '****';
            $out[$key] = ['value' => $masked, 'masked' => true];
        } else {
            $out[$key] = ['value' => $raw ?? '', 'masked' => false];
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'config' => $out]);
}

function handleConfigSet(PDO $pdo): void
{
    requirePlatformAdmin($pdo);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $updates = $body['updates'] ?? [];

    if (!is_array($updates) || empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'updates array required']);
        return;
    }

    $path = getEnvFilePath();
    $saved = [];
    $skipped = [];

    foreach ($updates as $key => $value) {
        $key = (string) $key;
        $value = (string) $value;
        if (!in_array($key, ALLOWED_KEYS, true)) {
            $skipped[] = $key;
            continue;
        }
        if (writeEnvKey($path, $key, $value)) {
            $saved[] = $key;
            // Also update in-process $_ENV / getenv
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        } else {
            $skipped[] = $key;
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'saved' => $saved, 'skipped' => $skipped]);
}
