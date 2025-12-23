<?php

namespace App\Middleware;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

final class CorrelationIdMiddleware
{
    public function __construct(private DiInterface $di)
    {
    }

    public function handle(Micro $app): bool
    {
        $req = $app->request;
        $res = $app->response;
        $ctx = $this->di->getShared("requestContext");

        $incoming = trim((string) $req->getHeader("X-Correlation-Id"));
        $cid = $this->isUuidV4($incoming) ? $incoming : $this->uuidV4();

        $ctx->correlationId = $cid;

        // Always return correlation id
        $res->setHeader("X-Correlation-Id", $cid);

        return true;
    }

    private function isUuidV4(string $v): bool
    {
        if ($v === "") {
            return false;
        }
        return (bool) preg_match(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
            $v
        );
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        // set version to 0100
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // set bits 6-7 to 10
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        $hex = bin2hex($data);
        return sprintf(
            "%s-%s-%s-%s-%s",
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
