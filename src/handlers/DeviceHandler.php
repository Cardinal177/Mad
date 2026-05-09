<?php

declare(strict_types=1);

function handleDeviceSetMode(): void
{
    $expectedDeviceToken = (string) (env_value('DEVICE_TOKEN', '') ?? '');
    $requestDeviceToken = (string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
    if ($expectedDeviceToken !== '' && !hash_equals($expectedDeviceToken, $requestDeviceToken)) {
        response(401, ['error' => 'Unauthorized device token']);
        return;
    }

    $data = parseJsonInput();
    $mode = (string) ($data['mode'] ?? '');

    if (!in_array($mode, ['in', 'out'], true)) {
        response(400, ['error' => 'Invalid mode, must be "in" or "out"']);
        return;
    }

    // Store in a simple key-value file or environment variable for now
    // File-based storage is simpler and doesn't require DB writes for every toggle
    $modeFile = sys_get_temp_dir() . '/mad_device_mode.txt';
    $content = json_encode([
        'mode' => $mode,
        'timestamp' => time(),
        'set_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if (!file_put_contents($modeFile, $content)) {
        response(500, ['error' => 'Failed to store mode']);
        return;
    }

    response(200, [
        'status' => 'ok',
        'mode' => $mode,
        'message' => 'Device mode set to ' . $mode,
    ]);
}

function handleDeviceGetMode(): void
{
    $expectedDeviceToken = (string) (env_value('DEVICE_TOKEN', '') ?? '');
    $requestDeviceToken = (string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
    if ($expectedDeviceToken !== '' && !hash_equals($expectedDeviceToken, $requestDeviceToken)) {
        response(401, ['error' => 'Unauthorized device token']);
        return;
    }

    $modeFile = sys_get_temp_dir() . '/mad_device_mode.txt';

    if (!file_exists($modeFile)) {
        response(200, [
            'status' => 'ok',
            'mode' => 'in',
            'message' => 'No mode set yet, defaulting to "in"',
        ]);
        return;
    }

    $content = file_get_contents($modeFile);
    $data = json_decode($content, true);

    if (!is_array($data) || !isset($data['mode'])) {
        response(200, [
            'status' => 'ok',
            'mode' => 'in',
            'message' => 'Mode file corrupted, defaulting to "in"',
        ]);
        return;
    }

    response(200, [
        'status' => 'ok',
        'mode' => (string) $data['mode'],
        'set_at' => (string) ($data['set_at'] ?? 'unknown'),
    ]);
}
