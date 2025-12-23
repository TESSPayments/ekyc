<?php

namespace App\Middleware;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

/**
 * Ensures route name is resolved early and stored in requestContext.
 */
final class RouteContextMiddleware
{
    public function __construct(private DiInterface $di) {}

    public function handle(Micro $app): bool
    {
        $ctx = $this->di->getShared('requestContext');
        if (!$this->di->has('routeResolver')) {
            return true;
        }

        $ctx->routeName = (string)$this->di->getShared('routeResolver')->resolveRouteName($app);
        return true;
    }
}