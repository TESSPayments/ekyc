<?php

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

return function (AbstractPdo $db): void {
    $email = getenv('SEED_ADMIN_EMAIL') ?: 'admin@local.test';
    $pass = getenv('SEED_ADMIN_PASSWORD') ?: 'ChangeMeNow!';

    if (strlen($pass) < 10) {
        throw new RuntimeException('SEED_ADMIN_PASSWORD must be at least 10 characters.');
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    // Insert user
    $db->execute(
        'INSERT INTO users (email, password_hash, status) VALUES (:e, :h, :s)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), status = VALUES(status)',
        ['e' => $email, 'h' => $hash, 's' => 'active']
    );

    $user = $db->fetchOne('SELECT id FROM users WHERE email = :e', DbEnum::FETCH_ASSOC, ['e' => $email]);
    if (!$user) {
        throw new RuntimeException('Failed to read back admin user');
    }
    $userId = (int)$user['id'];

    $role = $db->fetchOne('SELECT id FROM roles WHERE name = :n', DbEnum::FETCH_ASSOC, ['n' => 'admin']);
    if (!$role) {
        throw new RuntimeException('Admin role missing');
    }
    $roleId = (int)$role['id'];

    $db->execute(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)
         ON DUPLICATE KEY UPDATE user_id = user_id',
        ['u' => $userId, 'r' => $roleId]
    );
};