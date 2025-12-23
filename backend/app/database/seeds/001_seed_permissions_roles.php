<?php

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

return function (AbstractPdo $db): void {
    $permissions = [
        // Auth
        'auth:me', 'auth:logout',

        // Business capabilities (non-admin)
        'applications:create','applications:list','applications:read','applications:update','applications:submit','applications:reopen','applications:timeline','applications:summary',
        'workflow:read_state','workflow:assign','workflow:transition',
        'documents:upload_session','documents:create','documents:list','documents:read','documents:verify','documents:reject',
        'aml:run','aml:read_run','aml:read_latest','aml:list_matches','aml:resolve_match',
        'provisioning:create_merchant','merchants:read','merchants:update','merchants:go_live',
        'notifications:list','notifications:mark_read',
        'audit:list','audit:read',

        // Admin / governance (identity + RBAC)
        'admin:users:list','admin:users:create','admin:users:read','admin:users:update','admin:users:disable','admin:users:assign_roles',
        'admin:roles:list','admin:roles:read','admin:roles:create','admin:roles:update','admin:roles:delete','admin:roles:assign_permissions',
        'admin:permissions:list',
    ];

    // Insert permissions
    foreach ($permissions as $p) {
        $db->execute(
            'INSERT INTO permissions (name) VALUES (:n) ON DUPLICATE KEY UPDATE name = name',
            ['n' => $p]
        );
    }

    // Roles
    $roles = [
        'admin' => 'System administrator',
        'sales' => 'Sales user',
        'sales_manager' => 'Sales manager',
        'aml_operator' => 'AML operator',
        'aml_manager' => 'AML manager',
        'it_manager' => 'IT manager',
        'customer' => 'External customer',
    ];

    foreach ($roles as $name => $desc) {
        $db->execute(
            'INSERT INTO roles (name, description, is_system) VALUES (:n, :d, :s)
             ON DUPLICATE KEY UPDATE description = VALUES(description)',
            ['n' => $name, 'd' => $desc, 's' => ($name === 'admin') ? 1 : 0]
        );
    }

    // Admin gets everything
    $adminRole = $db->fetchOne('SELECT id FROM roles WHERE name = :n', DbEnum::FETCH_ASSOC, ['n' => 'admin']);
    if (!$adminRole) {
        throw new RuntimeException('Admin role not found');
    }
    $adminRoleId = (int)$adminRole['id'];

    $permRows = $db->fetchAll('SELECT id FROM permissions', DbEnum::FETCH_ASSOC);
    foreach ($permRows as $row) {
        $db->execute(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:r, :p)
             ON DUPLICATE KEY UPDATE role_id = role_id',
            ['r' => $adminRoleId, 'p' => (int)$row['id']]
        );
    }

    // Minimal role permission sets (tight defaults) - NO admin:* outside admin role
    $rolePerms = [
        'sales' => [
            'auth:me','auth:logout',
            'applications:create','applications:list','applications:read','applications:update','applications:submit','applications:timeline','applications:summary',
            'documents:upload_session','documents:create','documents:list','documents:read',
            'notifications:list','notifications:mark_read',
        ],
        'sales_manager' => [
            'auth:me','auth:logout',
            'applications:list','applications:read','applications:timeline','applications:summary','applications:reopen',
            'workflow:read_state','workflow:assign','workflow:transition',
            'documents:list','documents:read','documents:verify','documents:reject',
            'notifications:list','notifications:mark_read',
            'audit:list',
        ],
        'aml_operator' => [
            'auth:me','auth:logout',
            'applications:list','applications:read','applications:timeline','applications:summary',
            'aml:run','aml:read_run','aml:read_latest','aml:list_matches',
            'documents:list','documents:read',
            'notifications:list','notifications:mark_read',
        ],
        'aml_manager' => [
            'auth:me','auth:logout',
            'applications:list','applications:read','applications:timeline','applications:summary','applications:reopen',
            'workflow:read_state','workflow:assign','workflow:transition',
            'aml:read_run','aml:read_latest','aml:list_matches','aml:resolve_match',
            'documents:list','documents:read','documents:verify','documents:reject',
            'notifications:list','notifications:mark_read',
            'audit:list','audit:read',
        ],
        'it_manager' => [
            'auth:me','auth:logout',
            'applications:list','applications:read','applications:timeline','applications:summary',
            'workflow:read_state','workflow:assign','workflow:transition',
            'provisioning:create_merchant','merchants:read','merchants:update','merchants:go_live',
            'notifications:list','notifications:mark_read',
            'audit:list',
        ],
        'customer' => [
            'auth:me','auth:logout',
            'applications:create','applications:list','applications:read','applications:update','applications:submit','applications:timeline','applications:summary',
            'documents:upload_session','documents:create','documents:list','documents:read',
            'notifications:list','notifications:mark_read',
        ],
    ];

    foreach ($rolePerms as $roleName => $perms) {
        $roleRow = $db->fetchOne('SELECT id FROM roles WHERE name = :n', DbEnum::FETCH_ASSOC, ['n' => $roleName]);
        if (!$roleRow) continue;
        $roleId = (int)$roleRow['id'];

        foreach ($perms as $permName) {
            $permRow = $db->fetchOne('SELECT id FROM permissions WHERE name = :p', DbEnum::FETCH_ASSOC, ['p' => $permName]);
            if (!$permRow) continue;
            $db->execute(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:r, :p)
                 ON DUPLICATE KEY UPDATE role_id = role_id',
                ['r' => $roleId, 'p' => (int)$permRow['id']]
            );
        }
    }

    // OPTIONAL: Backward compatibility aliases
    // If you previously created permissions like users:update, you can keep them OR clean them up.
    // Recommended production path: delete old permissions after rollout.
};