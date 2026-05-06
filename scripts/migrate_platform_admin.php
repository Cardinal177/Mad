<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/handlers/AuthHandler.php';

$initials = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--initials=')) {
        $initials = strtoupper(trim(substr($arg, strlen('--initials='))));
    }
}

$pdo = db();

if (!usersHasPlatformAdminColumn($pdo)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN is_platform_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
    echo "Added users.is_platform_admin\n";
} else {
    echo "users.is_platform_admin already exists\n";
}

if ($initials !== null && $initials !== '') {
    $stmt = $pdo->prepare('UPDATE users SET is_platform_admin = 1 WHERE initials = ?');
    $stmt->execute([$initials]);
    echo 'Marked platform admin users for initials ' . $initials . ': ' . $stmt->rowCount() . "\n";
}