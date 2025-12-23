<?php
namespace App\Middleware;
use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use Phalcon\Di\DiInterface;
use App\Services\ApiVersion;

/**
 * Table-backed idempotency.
 *
 * Behavior:
 * - For selected methods (POST by default), require X-Idempotency-Key
 * - Compute request hash (raw JSON body)
 * - If existing record for (idem_key, route_name):
 * - If request_hash differs => 409
 * - Else replay stored response
 * - If not exists: allow request to proceed; capture response at end of request
 */
final class IdempotencyMiddleware
{
    private const METHODS = ['POST'];

    public function __construct(private DiInterface $di) {}

    public function handle(Micro $app): bool
    {
        $req = $app->request;
        $method = strtoupper((string)$req->getMethod());
        if (!in_array($method, self::METHODS, true)) {
            return true;
        }

        // Skip idempotency for auth endpoints
        $uri  = (string)$req->getURI();
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: $uri);
        if (str_starts_with($path, ApiVersion::path(ApiVersion::V1, 'auth'))) {
            return true;
        }

        $ctx = $this->di->getShared('requestContext');
        $routeName = (string)($ctx->routeName ?? '');
        if ($routeName === '') {
            return true;
        }

        $idemKey = trim((string)$req->getHeader('X-Idempotency-Key'));
        if ($idemKey === '') {
            $this->di->getShared('apiResponder')->error(
                'MISSING_IDEMPOTENCY_KEY',
                'X-Idempotency-Key header is required for this operation',
                400
            )->send();
            return false;
        }
        if (strlen($idemKey) > 128) {
            $this->di->getShared('apiResponder')->error('INVALID_IDEMPOTENCY_KEY', 'X-Idempotency-Key is too long', 400)->send();
            return false;
        }

        $raw = (string)$req->getRawBody();
        $requestHash = hash('sha256', $raw);

        if (!$this->di->has('db')) {
            return true;
        }

        /** @var \Phalcon\Db\Adapter\Pdo\AbstractPdo $db */
        $db = $this->di->getShared('db');

        $row = $db->fetchOne(
            'SELECT request_hash, response_body, response_status FROM idempotency_keys WHERE idem_key = :k AND route_name = :r AND expires_at > NOW() LIMIT 1',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            ['k' => $idemKey, 'r' => $routeName]
        );

        if (is_array($row) && $row) {
            if (!hash_equals((string)$row['request_hash'], $requestHash)) {
                $this->di->getShared('apiResponder')->error('IDEMPOTENCY_KEY_REUSE_MISMATCH', 'Idempotency key reuse with different payload', 409)->send();
                return false;
            }

            $payload = json_decode((string)$row['response_body'], true);
            $status = (int)$row['response_status'];

            /** @var Response $res */
            $res = $this->di->getShared('response');
            $res->setStatusCode($status);
            $res->setJsonContent($payload);
            $res->setHeader('X-Correlation-Id', (string)$ctx->correlationId);
            $res->send();
            return false;
        }

        $app->after(function () use ($app, $db, $idemKey, $routeName, $requestHash, $ctx) {
            try {
                $response = $app->response;
                $status = (int)$response->getStatusCode();
                $body = (string)$response->getContent();
                if ($body === '') {
                    return;
                }

                json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return;
                }

                $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->modify('+24 hours')
                    ->format('Y-m-d H:i:s');

                $db->execute(
                    'INSERT INTO idempotency_keys (idem_key, user_id, route_name, request_hash, response_body, response_status, correlation_id, created_at, expires_at)
                     VALUES (:k, :u, :r, :h, :b, :s, :c, NOW(), :e)
                     ON DUPLICATE KEY UPDATE response_body = VALUES(response_body), response_status = VALUES(response_status), correlation_id = VALUES(correlation_id), expires_at = VALUES(expires_at)',
                    [
                        'k' => $idemKey,
                        'u' => $ctx->userId,
                        'r' => $routeName,
                        'h' => $requestHash,
                        'b' => $body,
                        's' => $status,
                        'c' => (string)$ctx->correlationId,
                        'e' => $expiresAt,
                    ]
                );
            } catch (\Throwable) {
                // swallow
            }
        });

        return true;
    }
}