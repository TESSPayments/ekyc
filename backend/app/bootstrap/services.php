<?php
use Phalcon\Db\Adapter\Pdo\Mysql;

$di = $di ?? null;
if (!$di instanceof \Phalcon\Di\FactoryDefault) {
    throw new RuntimeException("DI container not available in services.php");
}

$di->setShared("permissionRegistry", function () {
    return new \App\Services\PermissionRegistry();
});

$di->setShared("db", function () use ($di) {
    $config = $di->getShared("config");
    return \App\Infrastructure\DbFactory::mysql($config->db);
});

$di->setShared("jwtService", function () use ($di) {
    $config = $di->getShared("config");
    return new \App\Services\JwtService(
        secret: (string) $config->security->jwtSecret,
        issuer: (string) $config->security->jwtIssuer,
        audience: (string) $config->security->jwtAudience,
        ttlSeconds: (int) $config->security->jwtTtlSeconds
    );
});

$di->setShared("rbacService", function () use ($di) {
    return new \App\Services\RbacService($di->getShared("db"));
});

$di->setShared('authRepository', function () use ($di) {
    return new \App\Domain\Repository\AuthRepository($di->getShared('db'));
});

$di->setShared('authService', function () use ($di) {
    return new \App\Services\AuthService(
        repo: $di->getShared('authRepository'),
        jwt: $di->getShared('jwtService'),
        rbac: $di->getShared('rbacService')
    );
});

$di->setShared('userRepository', function () use ($di) {
    return new \App\Domain\Repository\UserRepository($di->getShared('db'));
});

$di->setShared('userService', function () use ($di) {
    return new \App\Services\UserService($di->getShared('userRepository'));
});

$di->setShared('roleRepository', function () use ($di) {
    return new \App\Domain\Repository\RoleRepository($di->getShared('db'));
});

$di->setShared('roleService', function () use ($di) {
    return new \App\Services\RoleService($di->getShared('roleRepository'));
});

 $di->setShared('permissionRepository', function () use ($di) {
     return new \App\Domain\Repository\PermissionRepository($di->getShared('db'));
 });
 
 $di->setShared('permissionService', function () use ($di) {
     return new \App\Services\PermissionService($di->getShared('permissionRepository'));
 });

 $di->setShared('routeResolver', function () {
     return new \App\Services\RouteResolver();
 });

 $di->setShared('tokenRevocationService', function () use ($di) {
     return new \App\Services\TokenRevocationService($di->getShared('db'));
 });