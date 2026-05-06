<?php

declare(strict_types=1);

function getHeaderValue(string $name): ?string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    return (string) $value;
}

function getBearerToken(): ?string
{
    $auth = getHeaderValue('Authorization');
    if (!$auth) {
        return null;
    }

    if (stripos($auth, 'Bearer ') !== 0) {
        return null;
    }

    return trim(substr($auth, 7));
}

function requireValidDeviceToken(): bool
{
    $expectedDeviceToken = (string) (env_value('DEVICE_TOKEN', '') ?? '');
    if ($expectedDeviceToken === '') {
        return true;
    }

    $requestDeviceToken = (string) (getHeaderValue('X-Device-Token') ?? '');
    if ($requestDeviceToken !== '' && hash_equals($expectedDeviceToken, $requestDeviceToken)) {
        return true;
    }

    response(401, ['error' => 'Unauthorized device token']);
    return false;
}

function sendSmsViaInmobile(string $toPhoneE164, string $message): array
{
    $dryRun = strtolower((string) env_value('SMS_DRY_RUN', 'true')) === 'true';
    if ($dryRun) {
        return ['ok' => true, 'provider_ref' => 'dry-run'];
    }

    $url = (string) (env_value('INMOBILE_API_URL', '') ?? '');
    $apiToken = (string) (env_value('INMOBILE_API_TOKEN', '') ?? '');
    $sender = (string) (env_value('INMOBILE_SENDER', 'MAD') ?? 'MAD');

    if ($url === '' || $apiToken === '') {
        return ['ok' => false, 'error' => 'Missing INMOBILE_API_URL or INMOBILE_API_TOKEN'];
    }

    $normalized = preg_replace('/\D+/', '', $toPhoneE164 ?? '');
    if ($normalized === null || $normalized === '') {
        return ['ok' => false, 'error' => 'Invalid recipient phone number'];
    }

    $countryHint = substr($normalized, 0, 2);
    $messageId = 'mad-' . bin2hex(random_bytes(8));
    $validitySeconds = (int) (env_value('OTP_TTL_SECONDS', '300') ?? '300');
    if ($validitySeconds < 60) {
        $validitySeconds = 60;
    }

    $payload = [
        'messages' => [[
            'to' => $normalized,
            'countryHint' => $countryHint,
            'messageId' => $messageId,
            'respectBlacklist' => true,
            'validityPeriodInSeconds' => $validitySeconds,
            'from' => $sender,
            'text' => $message,
            'encoding' => 'auto',
        ]],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_USERPWD => 'mad:' . $apiToken,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'cURL error: ' . $curlErr];
    }

    $parsed = json_decode((string) $raw, true);
    $providerRef = '';
    if (is_array($parsed)) {
        $providerRef = (string) ($parsed['results'][0]['messageId'] ?? $parsed['results'][0]['id'] ?? $parsed['messageId'] ?? $parsed['id'] ?? '');
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['ok' => true, 'provider_ref' => $providerRef];
    }

    return ['ok' => false, 'error' => 'HTTP ' . $httpCode . ' from SMS provider', 'raw' => (string) $raw];
}

function handleAuthRequestCode(PDO $pdo): void
{
    if (!requireValidDeviceToken()) {
        return;
    }

    $data = parseJsonInput();
    $initials = strtoupper(trim((string) ($data['initials'] ?? '')));

    if ($initials === '') {
        response(400, ['error' => 'Missing initials']);
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT id, initials, full_name, phone_e164
         FROM users
         WHERE initials = ? AND is_active = 1
         ORDER BY id ASC
         LIMIT 1'
    );
    $stmt->execute([$initials]);
    $user = $stmt->fetch();

    if (!$user) {
        response(404, ['error' => 'User not found']);
        return;
    }

    $otpCode = (string) random_int(100000, 999999);
    $challengeId = bin2hex(random_bytes(16));
    $ttlSeconds = (int) (env_value('OTP_TTL_SECONDS', '300') ?? '300');
    if ($ttlSeconds < 60 || $ttlSeconds > 1800) {
        $ttlSeconds = 300;
    }

    $expiresAt = (new DateTimeImmutable())->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s');
    $codeHash = password_hash($otpCode, PASSWORD_BCRYPT);

    $message = 'Din Mad login-kode er: ' . $otpCode . '. Koden udloeber om ' . (int) floor($ttlSeconds / 60) . ' min.';
    $sms = sendSmsViaInmobile((string) $user['phone_e164'], $message);

    $stmt = $pdo->prepare(
        'INSERT INTO auth_otp_challenges
         (challenge_id, user_id, purpose, code_hash, attempts, max_attempts, requested_ip, user_agent, sent_via, sent_ok, provider_ref, expires_at)
         VALUES (?, ?, ?, ?, 0, 5, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $challengeId,
        (int) $user['id'],
        'login',
        $codeHash,
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'sms',
        $sms['ok'] ? 1 : 0,
        (string) ($sms['provider_ref'] ?? ''),
        $expiresAt,
    ]);

    $response = [
        'status' => 'ok',
        'challenge_id' => $challengeId,
        'expires_at' => $expiresAt,
        'sms_sent' => (bool) $sms['ok'],
    ];

    $debug = strtolower((string) env_value('APP_DEBUG', 'false')) === 'true';
    if ($debug) {
        $response['debug_code'] = $otpCode;
        if (!$sms['ok']) {
            $response['sms_error'] = $sms['error'] ?? 'Unknown SMS error';
        }
    }

    response(200, $response);
}

function handleAuthVerifyCode(PDO $pdo): void
{
    if (!requireValidDeviceToken()) {
        return;
    }

    $data = parseJsonInput();
    $challengeId = trim((string) ($data['challenge_id'] ?? ''));
    $code = trim((string) ($data['code'] ?? ''));

    if ($challengeId === '' || $code === '') {
        response(400, ['error' => 'Missing challenge_id or code']);
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.user_id, c.code_hash, c.attempts, c.max_attempts, c.expires_at, c.consumed_at,
                u.initials, u.full_name
         FROM auth_otp_challenges c
         INNER JOIN users u ON u.id = c.user_id
         WHERE c.challenge_id = ?
         LIMIT 1'
    );
    $stmt->execute([$challengeId]);
    $challenge = $stmt->fetch();

    if (!$challenge) {
        response(404, ['error' => 'Challenge not found']);
        return;
    }

    if ($challenge['consumed_at'] !== null) {
        response(409, ['error' => 'Challenge already used']);
        return;
    }

    if ((int) $challenge['attempts'] >= (int) $challenge['max_attempts']) {
        response(429, ['error' => 'Too many attempts']);
        return;
    }

    if (strtotime((string) $challenge['expires_at']) < time()) {
        response(410, ['error' => 'Code expired']);
        return;
    }

    $valid = password_verify($code, (string) $challenge['code_hash']);
    if (!$valid) {
        $stmt = $pdo->prepare('UPDATE auth_otp_challenges SET attempts = attempts + 1 WHERE id = ?');
        $stmt->execute([(int) $challenge['id']]);
        response(401, ['error' => 'Invalid code']);
        return;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE auth_otp_challenges SET consumed_at = NOW(), attempts = attempts + 1 WHERE id = ?');
    $stmt->execute([(int) $challenge['id']]);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $ttlHours = (int) (env_value('SESSION_TTL_HOURS', '24') ?? '24');
    if ($ttlHours < 1 || $ttlHours > 720) {
        $ttlHours = 24;
    }
    $expiresAt = (new DateTimeImmutable())->modify('+' . $ttlHours . ' hours')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO auth_sessions (user_id, token_hash, expires_at, last_seen_at)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([(int) $challenge['user_id'], $tokenHash, $expiresAt]);
    $pdo->commit();

    response(200, [
        'status' => 'ok',
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_at' => $expiresAt,
        'user' => [
            'id' => (int) $challenge['user_id'],
            'initials' => (string) $challenge['initials'],
            'full_name' => (string) $challenge['full_name'],
        ],
    ]);
}

function handleAuthMe(PDO $pdo): void
{
    $token = getBearerToken();
    if (!$token) {
        response(401, ['error' => 'Missing bearer token']);
        return;
    }

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT s.id AS session_id, s.user_id, s.expires_at, s.revoked_at,
                u.initials, u.full_name, u.phone_e164
         FROM auth_sessions s
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch();

    if (!$session) {
        response(401, ['error' => 'Invalid token']);
        return;
    }

    if ($session['revoked_at'] !== null) {
        response(401, ['error' => 'Session revoked']);
        return;
    }

    if (strtotime((string) $session['expires_at']) < time()) {
        response(401, ['error' => 'Session expired']);
        return;
    }

    $stmt = $pdo->prepare('UPDATE auth_sessions SET last_seen_at = NOW() WHERE id = ?');
    $stmt->execute([(int) $session['session_id']]);

    response(200, [
        'status' => 'ok',
        'user' => [
            'id' => (int) $session['user_id'],
            'initials' => (string) $session['initials'],
            'full_name' => (string) $session['full_name'],
            'phone_e164' => (string) $session['phone_e164'],
        ],
        'session_expires_at' => (string) $session['expires_at'],
    ]);
}
