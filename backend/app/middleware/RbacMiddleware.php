<?php
namespace App\Middleware;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;
use App\Services\ApiVersion;

final class RbacMiddleware
{
    public function __construct(private DiInterface $di) {}

    public function handle(Micro $app): bool
    {
        $req = $app->request;
        $uri  = (string)$req->getURI();
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: $uri);

        if (
            $path === ApiVersion::path(ApiVersion::V1, 'health') ||
            $path === ApiVersion::path(ApiVersion::V1, 'meta/routes') ||
            $path === ApiVersion::path(ApiVersion::V1, 'auth/login') ||
            $path === ApiVersion::path(ApiVersion::V1, 'auth/refresh')
        ) {
            return true;
        }

        $ctx = $this->di->getShared('requestContext');
        $routeName = (string)($ctx->routeName ?? '');
        if ($routeName === '') {
            return true; // not matched; let notFound handle
        }

        $registry = $this->di->getShared('permissionRegistry');
        $required = $registry->get($routeName);

        if ($required === null || $required === '') {
            $this->di->getShared('apiResponder')->error('FORBIDDEN', 'Permission mapping missing for route', 403, [
                'route' => $routeName,
            ])->send();
            return false;
        }

        if (!$ctx->userId) {
            $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Not authenticated', 401)->send();
            return false;
        }

        if (!$this->di->has('rbacService')) {
            $this->di->getShared('apiResponder')->error('FORBIDDEN', 'RBAC service not configured', 403)->send();
            return false;
        }

        $allowed = false;
        try {
            $allowed = (bool)$this->di->getShared('rbacService')->userHasPermission((int)$ctx->userId, (string)$required);
        } catch (\Throwable) {
            $allowed = false;
        }

        if (!$allowed) {
            $this->di->getShared('apiResponder')->error('FORBIDDEN', 'Access denied', 403, [
                'permission' => $required,
            ])->send();
            return false;
        }

        return true;
    }
}