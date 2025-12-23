<?php
namespace App\Middleware;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;
use App\Services\ApiVersion;

final class AuthMiddleware
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

        $auth = (string)$req->getHeader('Authorization');
        if ($auth === '' || !str_starts_with($auth, 'Bearer ')) {
            $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Missing bearer token', 401)->send();
            return false;
        }

        if (!$this->di->has('jwtService')) {
            $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Auth service not configured', 401)->send();
            return false;
        }

        $jwt = substr($auth, 7);
        $ctx = $this->di->getShared('requestContext');

        try {
            $payload = $this->di->getShared('jwtService')->validateToken($jwt);
        } catch (\Throwable) {
            $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Invalid token', 401)->send();
            return false;
        }

        $jti = (string)($payload['jti'] ?? '');
        if ($jti === '') {
            $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Token missing jti', 401)->send();
            return false;
        }

        if ($this->di->has('tokenRevocationService')) {
            try {
                if ($this->di->getShared('tokenRevocationService')->isRevoked($jti)) {
                    $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Token revoked', 401)->send();
                    return false;
                }
            } catch (\Throwable) {
                // Fail-closed
                $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Token validation failed', 401)->send();
                return false;
            }
        }

        $userId = (int)($payload['sub'] ?? 0);
        if ($userId <= 0) {
            $this->di->getShared('apiResponder')->error('UNAUTHORIZED', 'Invalid token subject', 401)->send();
            return false;
        }

        $ctx->userId = $userId;
        $ctx->roles = isset($payload['roles']) && is_array($payload['roles']) ? $payload['roles'] : [];

        return true;
    }
}