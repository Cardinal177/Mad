<?php

declare(strict_types=1);

function handleAdminListUsers(PDO $pdo): void
{
    requirePlatformAdmin($pdo);

    $stmt = $pdo->query(
        'SELECT id, initials, full_name, phone_e164, is_active,
                COALESCE(is_platform_admin, 0) AS is_platform_admin,
                created_at
         FROM users
         ORDER BY full_name ASC, id ASC'
    );
    $users = $stmt->fetchAll() ?: [];

    $membershipsStmt = $pdo->query(
        'SELECT hu.user_id, h.id AS household_id, h.name AS household_name, hu.role
         FROM household_users hu
         INNER JOIN households h ON h.id = hu.household_id
         ORDER BY h.name ASC, hu.user_id ASC'
    );
    $memberships = $membershipsStmt->fetchAll() ?: [];

    $membershipsByUser = [];
    foreach ($memberships as $membership) {
        $userId = (int) $membership['user_id'];
        $membershipsByUser[$userId][] = [
            'id' => (int) $membership['household_id'],
            'name' => (string) $membership['household_name'],
            'role' => (string) $membership['role'],
        ];
    }

    response(200, [
        'status' => 'ok',
        'users' => array_map(static function (array $user) use ($membershipsByUser): array {
            $userId = (int) $user['id'];
            return [
                'id' => $userId,
                'initials' => (string) $user['initials'],
                'full_name' => (string) $user['full_name'],
                'phone_e164' => (string) $user['phone_e164'],
                'is_active' => (int) $user['is_active'] === 1,
                'is_platform_admin' => (int) ($user['is_platform_admin'] ?? 0) === 1,
                'households' => $membershipsByUser[$userId] ?? [],
            ];
        }, $users),
    ]);
}

function handleAdminListHouseholds(PDO $pdo): void
{
    requirePlatformAdmin($pdo);

    $stmt = $pdo->query(
        'SELECT h.id, h.name, h.created_at,
                COUNT(DISTINCT hu.user_id) AS user_count,
                COUNT(DISTINCT hl.id) AS location_count
         FROM households h
         LEFT JOIN household_users hu ON hu.household_id = h.id
         LEFT JOIN household_locations hl ON hl.household_id = h.id
         GROUP BY h.id, h.name, h.created_at
         ORDER BY h.name ASC, h.id ASC'
    );

    response(200, [
        'status' => 'ok',
        'households' => $stmt->fetchAll() ?: [],
    ]);
}

function handleAdminCreateHousehold(PDO $pdo): void
{
    $session = requirePlatformAdmin($pdo);
    $data = parseJsonInput();
    $name = trim((string) ($data['name'] ?? ''));

    if ($name === '') {
        response(400, ['error' => 'Missing household name']);
        return;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO households (name) VALUES (?)');
    $stmt->execute([$name]);
    $householdId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO household_locations (household_id, name) VALUES (?, ?)');
    $stmt->execute([$householdId, 'Standard']);

    if (!empty($data['admin_user_id'])) {
        $role = 'admin';
        $stmt = $pdo->prepare(
            'INSERT INTO household_users (household_id, user_id, role)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        );
        $stmt->execute([$householdId, (int) $data['admin_user_id'], $role]);
    }

    $pdo->commit();

    response(201, [
        'status' => 'ok',
        'household' => [
            'id' => $householdId,
            'name' => $name,
            'created_by_user_id' => (int) $session['user_id'],
        ],
    ]);
}

function handleAdminCreateUser(PDO $pdo): void
{
    requirePlatformAdmin($pdo);
    $data = parseJsonInput();

    $initials = strtoupper(trim((string) ($data['initials'] ?? '')));
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $phone = trim((string) ($data['phone_e164'] ?? ''));
    $isActive = !isset($data['is_active']) || (int) $data['is_active'] === 1 ? 1 : 0;
    $isPlatformAdmin = isset($data['is_platform_admin']) && (int) $data['is_platform_admin'] === 1 ? 1 : 0;

    if ($initials === '' || $fullName === '' || $phone === '') {
        response(400, ['error' => 'Missing initials, full_name or phone_e164']);
        return;
    }

    $columns = ['initials', 'full_name', 'phone_e164', 'is_active'];
    $values = [$initials, $fullName, $phone, $isActive];

    if (usersHasPlatformAdminColumn($pdo)) {
        $columns[] = 'is_platform_admin';
        $values[] = $isPlatformAdmin;
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    $userId = (int) $pdo->lastInsertId();

    response(201, [
        'status' => 'ok',
        'user' => [
            'id' => $userId,
            'initials' => $initials,
            'full_name' => $fullName,
            'phone_e164' => $phone,
            'is_active' => $isActive === 1,
            'is_platform_admin' => $isPlatformAdmin === 1,
        ],
    ]);
}

function handleAdminAssignUserToHousehold(PDO $pdo): void
{
    requirePlatformAdmin($pdo);
    $data = parseJsonInput();

    $householdId = (int) ($data['household_id'] ?? 0);
    $userId = (int) ($data['user_id'] ?? 0);
    $role = (string) ($data['role'] ?? 'member');

    if ($householdId < 1 || $userId < 1) {
        response(400, ['error' => 'Missing household_id or user_id']);
        return;
    }

    if (!in_array($role, ['owner', 'admin', 'member'], true)) {
        response(400, ['error' => 'Invalid role']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM households WHERE id = ? LIMIT 1');
    $stmt->execute([$householdId]);
    if (!$stmt->fetch()) {
        response(404, ['error' => 'Household not found']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        response(404, ['error' => 'User not found']);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO household_users (household_id, user_id, role)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE role = VALUES(role)'
    );
    $stmt->execute([$householdId, $userId, $role]);

    response(200, [
        'status' => 'ok',
        'membership' => [
            'household_id' => $householdId,
            'user_id' => $userId,
            'role' => $role,
        ],
    ]);
}