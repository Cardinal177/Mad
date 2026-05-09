<?php

declare(strict_types=1);

function handleDeviceSetMode(): void
{
    // No token required for browser POST - anyone on the network can set mode
    // Token validation is only needed for ESP32 polling (GET endpoint)
    $data = parseJsonInput();
    $mode = (string) ($data['mode'] ?? '');

    if (!in_array($mode, ['in', 'out'], true)) {
        response(400, ['error' => 'Invalid mode, must be "in" or "out"']);
        return;
    }

    // Store in a simple key-value file for fast access
    $modeFile = sys_get_temp_dir() . '/mad_device_mode.txt';
    $content = json_encode([
        'mode' => $mode,
        'timestamp' => time(),
        'set_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if (!file_put_contents($modeFile, $content)) {
        error_log('[DeviceHandler] Failed to write mode file: ' . $modeFile);
        response(500, ['error' => 'Failed to store mode']);
        return;
    }
    
    error_log('[DeviceHandler] Mode set to: ' . $mode . ' (file: ' . $modeFile . ')');

    response(200, [
        'status' => 'ok',
        'mode' => $mode,
        'message' => 'Device mode set to ' . $mode,
    ]);
}

function handleDeviceGetMode(): void
{
    // No token required for browser GET - allow local network to read mode
    $modeFile = sys_get_temp_dir() . '/mad_device_mode.txt';

    if (!file_exists($modeFile)) {
        error_log('[DeviceHandler] Mode file not found: ' . $modeFile . ', defaulting to "in"');
        response(200, [
            'status' => 'ok',
            'mode' => 'in',
            'message' => 'No mode file, defaulting to in',
        ]);
        return;
    }

    $content = file_get_contents($modeFile);
    $data = json_decode($content, true);

    if (!is_array($data) || !isset($data['mode'])) {
        error_log('[DeviceHandler] Corrupted mode file: ' . $modeFile);
        response(200, [
            'status' => 'ok',
            'mode' => 'in',
            'message' => 'Mode file corrupted, defaulting to in',
        ]);
        return;
    }
    
    $mode = (string) $data['mode'];
    error_log('[DeviceHandler] Mode GET: ' . $mode . ' (set_at: ' . ($data['set_at'] ?? 'unknown') . ')');

    response(200, [
        'status' => 'ok',
        'mode' => $mode,
        'set_at' => (string) ($data['set_at'] ?? 'unknown'),
    ]);
}
