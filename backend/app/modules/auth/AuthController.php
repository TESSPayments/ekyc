<?php
namespace App\Modules\Auth;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

final class AuthController
{
    public function __construct(private DiInterface $di)
    {
    }

    public function login(Micro $app)
    {
        $responder = $this->di->getShared("apiResponder");
        $ctx = $this->di->getShared("requestContext");
        $req = $app->request;

        $body = $req->getJsonRawBody(true);
        if (!is_array($body)) {
            return $responder->error(
                "INVALID_JSON",
                "Invalid JSON payload",
                400
            );
        }

        $email = (string) ($body["email"] ?? "");
        $password = (string) ($body["password"] ?? "");

        try {
            $auth = $this->di->getShared("authService");
            $data = $auth->login(
                email: $email,
                password: $password,
                correlationId: (string) $ctx->correlationId,
                ip: (string) $req->getClientAddress(true),
                ua: (string) $req->getUserAgent()
            );
            return $responder->ok($data, 200);
        } catch (\InvalidArgumentException $e) {
            return $responder->error("VALIDATION_ERROR", $e->getMessage(), 400);
        } catch (\Throwable) {
            return $responder->error(
                "UNAUTHORIZED",
                "Invalid credentials",
                401
            );
        }
    }

    public function refresh(Micro $app)
    {
        $responder = $this->di->getShared("apiResponder");
        $ctx = $this->di->getShared("requestContext");
        $req = $app->request;

        $body = $req->getJsonRawBody(true);
        if (!is_array($body)) {
            return $responder->error(
                "INVALID_JSON",
                "Invalid JSON payload",
                400
            );
        }

        $refreshToken = (string) ($body["refresh_token"] ?? "");

        try {
            $auth = $this->di->getShared("authService");
            $data = $auth->refresh(
                refreshToken: $refreshToken,
                correlationId: (string) $ctx->correlationId,
                ip: (string) $req->getClientAddress(true),
                ua: (string) $req->getUserAgent()
            );
            return $responder->ok($data, 200);
        } catch (\InvalidArgumentException $e) {
            return $responder->error("VALIDATION_ERROR", $e->getMessage(), 400);
        } catch (\Throwable) {
            return $responder->error(
                "UNAUTHORIZED",
                "Invalid refresh token",
                401
            );
        }
    }

    public function logout(Micro $app)
    {
        $responder = $this->di->getShared("apiResponder");
        $req = $app->request;

        $req = $app->request;
        $auth = (string)$req->getHeader('Authorization');
        if ($auth !== '' && str_starts_with($auth, 'Bearer ')) {
            $jwt = substr($auth, 7);
            try {
                $payload = $this->di->getShared('jwtService')->validateToken($jwt);
                $userId = (int)($payload['sub'] ?? 0);
                $jti = (string)($payload['jti'] ?? '');
                $exp = (int)($payload['exp'] ?? 0);

                $ctx = $this->di->getShared('requestContext');
                $this->di->getShared('tokenRevocationService')->revoke(
                    jti: $jti,
                    userId: $userId,
                    tokenType: 'access',
                    expUnix: $exp,
                    reason: 'logout',
                    correlationId: (string)($ctx->correlationId ?? null)
                );
                return $responder->ok((object) [], 200);
            } catch (\Throwable) {
                return $responder->ok((object) [], 200);
            }
        }
    }

    public function me(Micro $app)
    {
        $responder = $this->di->getShared("apiResponder");
        $ctx = $this->di->getShared("requestContext");

        if (!$ctx->userId) {
            return $responder->error("UNAUTHORIZED", "Not authenticated", 401);
        }

        try {
            $profile = $this->di
                ->getShared("authService")
                ->me((int) $ctx->userId);
            return $responder->ok($profile, 200);
        } catch (\Throwable) {
            return $responder->error("NOT_FOUND", "User not found", 404);
        }
    }
}
