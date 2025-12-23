<?php
namespace App\Middleware;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

/**
 * Simple in-process rate limiter (development default).
 *
 * Production recommendation:
 * - move to Redis or API gateway/WAF rate limiting.
 */
final class RateLimitMiddleware
{
    private static array $buckets = []; // key => [count, windowStart]

    public function __construct(private DiInterface $di)
    {
    }

    public function handle(Micro $app): bool
    {
        $config = $this->di->getShared("config");

        // Disable in debug local if desired
        if (!empty($config->app->debug)) {
            return true;
        }

        $req = $app->request;
        $ctx = $this->di->getShared("requestContext");

        $ip = (string) $req->getClientAddress(true);
        $user = $ctx->userId ? (string) $ctx->userId : "anon";
        $key = $user . "|" . $ip;

        $limit = 120; // requests
        $window = 60; // seconds

        $now = time();
        if (!isset(self::$buckets[$key])) {
            self::$buckets[$key] = ["count" => 0, "start" => $now];
        }

        $bucket = &self::$buckets[$key];
        if ($now - $bucket["start"] >= $window) {
            $bucket["count"] = 0;
            $bucket["start"] = $now;
        }

        $bucket["count"]++;
        if ($bucket["count"] > $limit) {
            $retry = $window - ($now - $bucket["start"]);
            $app->response->setHeader("Retry-After", (string) max(1, $retry));
            $this->di
                ->getShared("apiResponder")
                ->error("RATE_LIMITED", "Too many requests", 429)
                ->send();
            return false;
        }

        return true;
    }
}
