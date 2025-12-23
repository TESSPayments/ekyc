<?php

namespace App\Middleware;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

final class CorsMiddleware
{
    public function __construct(private DiInterface $di)
    {
    }

    public function handle(Micro $app): bool
    {
        $config = $this->di->getShared("config");
        $req = $app->request;
        $res = $app->response;

        $origin = (string) $req->getHeader("Origin");

        // If no origin header, treat as non-CORS and continue.
        if ($origin === "") {
            return true;
        }

        $allowedOrigins = $config->cors->allowedOrigins;
        $isWildcard = in_array("*", $allowedOrigins, true);

        $isAllowed = $isWildcard || in_array($origin, $allowedOrigins, true);
        if ($isAllowed) {
            $res->setHeader(
                "Access-Control-Allow-Origin",
                $isWildcard ? "*" : $origin
            );
            $res->setHeader("Vary", "Origin");
            $res->setHeader("Access-Control-Allow-Credentials", "true");
            $res->setHeader(
                "Access-Control-Allow-Methods",
                implode(", ", $config->cors->allowedMethods)
            );
            $res->setHeader(
                "Access-Control-Allow-Headers",
                implode(", ", $config->cors->allowedHeaders)
            );
            $res->setHeader(
                "Access-Control-Expose-Headers",
                implode(", ", $config->cors->exposedHeaders)
            );
            $res->setHeader(
                "Access-Control-Max-Age",
                (string) $config->cors->maxAge
            );
        }

        // Preflight
        if (strtoupper((string) $req->getMethod()) === "OPTIONS") {
            // If origin isn't allowed, respond 403
            if (!$isAllowed) {
                return $this->di
                    ->getShared("apiResponder")
                    ->error("CORS_DENIED", "CORS origin not allowed", 403);
            }

            $res->setStatusCode(204);
            $res->setContent("");
            $res->send();
            return false;
        }

        // If origin is provided but not allowed, block request
        if (!$isAllowed) {
            $this->di
                ->getShared("apiResponder")
                ->error("CORS_DENIED", "CORS origin not allowed", 403)
                ->send();
            return false;
        }

        return true;
    }
}
