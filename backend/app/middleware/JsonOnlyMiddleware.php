<?php
namespace App\Middleware;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

final class JsonOnlyMiddleware
{
    private const EMPTY_BODY_ALLOWLIST = [
        'v1.auth.logout',
        'v1.admin.users.disable',
        'v1.admin.roles.delete'
    ];

    public function __construct(private DiInterface $di) {}

    public function handle(Micro $app): bool
    {
        $req = $app->request;
        $method = strtoupper((string)$req->getMethod());

        if (in_array($method, ['GET','HEAD','OPTIONS'], true)) {
            return true;
        }

        $contentType = strtolower((string)$req->getContentType());
        if ($contentType === '' || !str_starts_with($contentType, 'application/json')) {
            $this->di->getShared('apiResponder')->error(
                'UNSUPPORTED_MEDIA_TYPE',
                'Requests must use Content-Type: application/json',
                415,
                ['received' => $contentType ?: '(none)', 'expected' => 'application/json']
            )->send();
            return false;
        }

        $raw = (string)$req->getRawBody();
        if ($raw === '') {
            $ctx = $this->di->getShared('requestContext');
            $routeName = (string)($ctx->routeName ?? '');

            if ($routeName !== '' && in_array($routeName, self::EMPTY_BODY_ALLOWLIST, true)) {
                return true;
            }

            $this->di->getShared('apiResponder')->error('INVALID_JSON', 'Empty request body; expected JSON', 400)->send();
            return false;
        }

        json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->di->getShared('apiResponder')->error('INVALID_JSON', 'Malformed JSON payload', 400, [
                'json_error' => json_last_error_msg(),
            ])->send();
            return false;
        }

        return true;
    }
}