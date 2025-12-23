<?php
/**
 * Middleware Route Name Resolution (Phalcon Micro)
 *
 * Why:
 *  - In some Phalcon Micro setups, $app->getRouter()->getMatchedRoute() may be null
 *    when called inside middleware (depending on when router->handle() is executed).
 *  - This adds a tiny RouteResolver service that guarantees route matching for the current request
 *    and stores the resolved route name in requestContext for consistent use across middleware.
 *
 * Files included:
 *  - app/Services/RouteResolver.php
 *  - app/Middleware/RouteContextMiddleware.php
 *  - PATCHES to: app/Middleware/IdempotencyMiddleware.php, AuthMiddleware.php, RbacMiddleware.php
 *  - DI registration snippet
 *
 * Integration order (recommended):
 *  1) CorrelationIdMiddleware
 *  2) CorsMiddleware
 *  3) RouteContextMiddleware   <-- NEW (ensures matched route name is available)
 *  4) JsonOnlyMiddleware
 *  5) AuthMiddleware
 *  6) RbacMiddleware
 *  7) IdempotencyMiddleware (optional: can also run before Auth/RBAC, but needs route name)
 */

namespace App\Services;

use Phalcon\Mvc\Micro;

final class RouteResolver
{
    /**
     * Resolve the current route name.
     *
     * Returns:
     *  - route name string if matched and named
     *  - empty string if no route matched
     */
    public function resolveRouteName(Micro $app): string
    {
        $router = $app->getRouter();
        if (!$router) {
            return '';
        }

        // Prefer already matched route
        if (method_exists($router, 'getMatchedRoute')) {
            $matched = $router->getMatchedRoute();
            if ($matched) {
                $name = (string)$matched->getName();
                if ($name !== '') {
                    return $name;
                }
            }
        }

        // Force router to handle current request path (Micro sometimes does this later)
        $req = $app->request;
        $uri = (string)$req->getURI();
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : $uri;
        if ($path === '') {
            $path = '/';
        }

        try {
            $router->handle($path);
        } catch (\Throwable) {
            // ignore routing errors
        }

        if (method_exists($router, 'getMatchedRoute')) {
            $matched = $router->getMatchedRoute();
            if ($matched) {
                return (string)$matched->getName();
            }
        }

        return '';
    }
}