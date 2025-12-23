<?php

use Phalcon\Mvc\Micro;
use App\Services\ApiVersion;

/** @var \Phalcon\Di\FactoryDefault $di */
/** @var Micro $app */

$registry = $di->getShared('permissionRegistry');

/**
 * Unified binder:
 *  - Sets route name
 *  - Registers permission requirement in PermissionRegistry
 */
$bind = function ($route, string $name, ?string $permission) use ($registry) {
    $route->setName($name);
    $registry->set($name, $permission);
    return $route;
};

// Versioned path helper for v1
$V1 = function (string $path): string {
    return ApiVersion::path(ApiVersion::V1, $path);
};

// ======================================================
// V1 ROUTES
// ======================================================

// Public/meta routes (no auth)
$bind($app->get($V1('health'), function () use ($di) {
    return $di->getShared('apiResponder')->ok(['status' => 'ok'], 200);
}), 'v1.health', null);

$bind($app->get($V1('meta/routes'), function () use ($di) {
    // Optional: introspection endpoint for debugging; restrict in production if needed.
    $registry = $di->getShared('permissionRegistry');
    return $di->getShared('apiResponder')->ok(['routes' => $registry->all()], 200);
}), 'v1.meta.routes', null);

// Auth routes
$authController = new \App\Modules\Auth\AuthController($di);
$bind($app->post($V1('auth/login'), fn() => $authController->login($app)), 'v1.auth.login', null);
$bind($app->post($V1('auth/refresh'), fn() => $authController->refresh($app)), 'v1.auth.refresh', null);
$bind($app->post($V1('auth/logout'), fn() => $authController->logout($app)), 'v1.auth.logout', 'auth:logout');
$bind($app->get($V1('auth/me'), fn() => $authController->me($app)), 'v1.auth.me', 'auth:me');

// RBAC - Admin: Users
$userController = new \App\Modules\Admin\UserController($di);
$bind($app->get($V1('admin/users'), fn() => $userController->list($app)), 'v1.admin.users.list', 'admin:users:list');
$bind($app->post($V1('admin/users'), fn() => $userController->create($app)), 'v1.admin.users.create', 'admin:users:create');
$bind($app->get($V1('admin/users/{id:[0-9]+}'), fn($id) => $userController->read($app, (int)$id)), 'v1.admin.users.read', 'admin:users:read');
$bind($app->patch($V1('admin/users/{id:[0-9]+}'), fn($id) => $userController->update($app, (int)$id)), 'v1.admin.users.update', 'admin:users:update');
$bind($app->post($V1('admin/users/{id:[0-9]+}/disable'), fn($id) => $userController->disable($app, (int)$id)), 'v1.admin.users.disable', 'admin:users:disable');
$bind($app->post($V1('admin/users/{id:[0-9]+}/roles'), fn($id) => $userController->assignRoles($app, (int)$id)), 'v1.admin.users.assign_roles', 'admin:users:assign_roles');

// RBAC - Admin: Roles
$roleController = new \App\Modules\Admin\RoleController($di);
$bind($app->get($V1('admin/roles'), fn() => $roleController->list($app)), 'v1.admin.roles.list', 'admin:roles:list');
$bind($app->post($V1('admin/roles'), fn() => $roleController->create($app)), 'v1.admin.roles.create', 'admin:roles:create');
$bind($app->get($V1('admin/roles/{id:[0-9]+}'), fn($id) => $roleController->read($app, (int)$id)), 'v1.admin.roles.read', 'admin:roles:read');
$bind($app->patch($V1('admin/roles/{id:[0-9]+}'), fn($id) => $roleController->update($app, (int)$id)), 'v1.admin.roles.update', 'admin:roles:update');
$bind($app->delete($V1('admin/roles/{id:[0-9]+}'), fn($id) => $roleController->delete($app, (int)$id)), 'v1.admin.roles.delete', 'admin:roles:delete');
$bind($app->post($V1('admin/roles/{id:[0-9]+}/permissions'), fn($id) => $roleController->assignPermissions($app, (int)$id)), 'v1.admin.roles.assign_permissions', 'admin:roles:assign_permissions');

// RBAC - Admin: Permissions
$permController = new \App\Modules\Admin\PermissionController($di);
$bind($app->get($V1('admin/permissions'), fn() => $permController->list($app)), 'v1.admin.permissions.list', 'admin:permissions:list');
