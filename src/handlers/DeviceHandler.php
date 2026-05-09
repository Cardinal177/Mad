<?php

declare(strict_types=1);

function handleDeviceSetMode(): void
{
    // No token required for browser POST - anyone on the network can set mode
    // Token validation is only needed for ESP32 polling (GET endpoint)
    $data = parseJsonInput();
    $mode = (string) ($data['mode'] ?? '');
    $householdId = isset($data['household_id']) ? max(1, (int) $data['household_id']) : 1;
    $locationId = isset($data['location_id']) ? max(1, (int) $data['location_id']) : 1;

    if (!in_array($mode, ['in', 'out'], true)) {
        response(400, ['error' => 'Invalid mode, must be "in" or "out"']);
        return;
    }

    // Store in a simple key-value file for fast access
    $modeFile = sys_get_temp_dir() . '/mad_device_mode.txt';
    $content = json_encode([
        'mode' => $mode,
        'household_id' => $householdId,
        'location_id' => $locationId,
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
        'household_id' => $householdId,
        'location_id' => $locationId,
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
        'household_id' => (int) ($data['household_id'] ?? 1),
        'location_id' => (int) ($data['location_id'] ?? 1),
        'set_at' => (string) ($data['set_at'] ?? 'unknown'),
    ]);
}

function handleDeviceGetLastScan(): void
{
    $scanFile = sys_get_temp_dir() . '/mad_last_device_scan.txt';

    if (!file_exists($scanFile)) {
        response(200, [
            'status' => 'ok',
            'scan' => null,
            'message' => 'No scans yet',
        ]);
        return;
    }

    $content = file_get_contents($scanFile);
    $data = json_decode((string) $content, true);

    if (!is_array($data) || !isset($data['barcode']) || !isset($data['timestamp'])) {
        response(200, [
            'status' => 'ok',
            'scan' => null,
            'message' => 'Invalid scan data',
        ]);
        return;
    }

    response(200, [
        'status' => 'ok',
        'scan' => [
            'barcode' => (string) ($data['barcode'] ?? ''),
            'movement_type' => (string) ($data['movement_type'] ?? 'in'),
            'household_id' => (int) ($data['household_id'] ?? 1),
            'location_id' => (int) ($data['location_id'] ?? 1),
            'duplicate_ignored' => (bool) ($data['duplicate_ignored'] ?? false),
            'timestamp' => (int) ($data['timestamp'] ?? 0),
            'set_at' => (string) ($data['set_at'] ?? ''),
        ],
    ]);
}
