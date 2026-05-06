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

function usersHasPlatformAdminColumn(PDO $pdo): bool
{
    static $hasColumn = null;

    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_platform_admin'");
    $hasColumn = (bool) $stmt->fetch();

    return $hasColumn;
}

function getAuthenticatedSession(PDO $pdo): ?array
{
    $token = getBearerToken();
    if (!$token) {
        return null;
    }

    $tokenHash = hash('sha256', $token);

    $platformAdminSelect = usersHasPlatformAdminColumn($pdo)
        ? 'COALESCE(u.is_platform_admin, 0) AS is_platform_admin'
        : '0 AS is_platform_admin';

    $stmt = $pdo->prepare(
        'SELECT s.id AS session_id, s.user_id, s.expires_at, s.revoked_at,
                u.initials, u.full_name, u.phone_e164,
                ' . $platformAdminSelect . '
         FROM auth_sessions s
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    if ($session['revoked_at'] !== null) {
        return null;
    }

    if (strtotime((string) $session['expires_at']) < time()) {
        return null;
    }

    return $session;
}

function requireAuthenticatedSession(PDO $pdo): array
{
    $session = getAuthenticatedSession($pdo);
    if (!$session) {
        response(401, ['error' => 'Unauthorized']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE auth_sessions SET last_seen_at = NOW() WHERE id = ?');
    $stmt->execute([(int) $session['session_id']]);

    return $session;
}

function getUserHouseholdMemberships(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT h.id, h.name, hu.role
         FROM household_users hu
         INNER JOIN households h ON h.id = hu.household_id
         WHERE hu.user_id = ?
         ORDER BY h.name ASC, h.id ASC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll() ?: [];
}

function resolveAccessibleHouseholdId(PDO $pdo, array $session, ?int $requestedHouseholdId = null): int
{
    $memberships = getUserHouseholdMemberships($pdo, (int) $session['user_id']);
    $allowedIds = array_map(static fn(array $membership): int => (int) $membership['id'], $memberships);

    if ($requestedHouseholdId !== null && $requestedHouseholdId > 0) {
        if (in_array($requestedHouseholdId, $allowedIds, true)) {
            return $requestedHouseholdId;
        }

        response(403, ['error' => 'Forbidden household']);
        exit;
    }

    if ($allowedIds === []) {
        response(403, ['error' => 'No household access']);
        exit;
    }

    return $allowedIds[0];
}

function requirePlatformAdmin(PDO $pdo): array
{
    $session = requireAuthenticatedSession($pdo);
    if ((int) ($session['is_platform_admin'] ?? 0) !== 1) {
        response(403, ['error' => 'Platform admin access required']);
        exit;
    }

    return $session;
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

    $memberships = getUserHouseholdMemberships($pdo, (int) $challenge['user_id']);

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
        'households' => array_map(static function (array $membership): array {
            return [
                'id' => (int) $membership['id'],
                'name' => (string) $membership['name'],
                'role' => (string) $membership['role'],
            ];
        }, $memberships),
        'active_household_id' => $memberships !== [] ? (int) $memberships[0]['id'] : null,
    ]);
}

function handleAuthMe(PDO $pdo): void
{
    $session = requireAuthenticatedSession($pdo);
    $memberships = getUserHouseholdMemberships($pdo, (int) $session['user_id']);

    response(200, [
        'status' => 'ok',
        'user' => [
            'id' => (int) $session['user_id'],
            'initials' => (string) $session['initials'],
            'full_name' => (string) $session['full_name'],
            'phone_e164' => (string) $session['phone_e164'],
            'is_platform_admin' => (int) ($session['is_platform_admin'] ?? 0) === 1,
        ],
        'households' => array_map(static function (array $membership): array {
            return [
                'id' => (int) $membership['id'],
                'name' => (string) $membership['name'],
                'role' => (string) $membership['role'],
            ];
        }, $memberships),
        'active_household_id' => $memberships !== [] ? (int) $memberships[0]['id'] : null,
        'session_expires_at' => (string) $session['expires_at'],
    ]);
}

function handleAuthTestSms(PDO $pdo): void
{
    if (!requireValidDeviceToken()) {
        return;
    }

    $data = parseJsonInput();
    $initials = strtoupper(trim((string) ($data['initials'] ?? '')));
    $phone = trim((string) ($data['phone_e164'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));

    if ($message === '') {
        $message = 'Mad test SMS sendt ' . date('Y-m-d H:i:s');
    }

    if ($phone === '' && $initials === '') {
        response(400, ['error' => 'Provide initials or phone_e164']);
        return;
    }

    if ($phone === '' && $initials !== '') {
        $stmt = $pdo->prepare(
            'SELECT phone_e164, full_name
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

        $phone = (string) $user['phone_e164'];
    }

    $sms = sendSmsViaInmobile($phone, $message);
    if (!$sms['ok']) {
        response(502, [
            'error' => 'SMS send failed',
            'details' => $sms['error'] ?? 'Unknown error',
            'raw' => $sms['raw'] ?? null,
        ]);
        return;
    }

    response(200, [
        'status' => 'ok',
        'sms_sent' => true,
        'provider_ref' => $sms['provider_ref'] ?? null,
        'recipient' => $phone,
        'message' => $message,
    ]);
}
