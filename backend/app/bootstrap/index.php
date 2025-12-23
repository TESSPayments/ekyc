<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Micro;
use Phalcon\Http\Response;
use App\Http\RequestContext;

// Composer autoload (ensure you have phalcon/phalcon and any libs installed)
$root = dirname(__DIR__, 2);
$autoload = $root . "/vendor/autoload.php";
if (is_file($autoload)) {
    require $autoload;
}

// Basic env helper
$env = static function (string $key, $default = null) {
    $val = getenv($key);
    return $val === false || $val === "" ? $default : $val;
};

$di = new FactoryDefault();

// -------------------------
// Services: Response
// -------------------------
$di->setShared("response", function () {
    $response = new Response();
    $response->setContentType("application/json", "utf-8");
    return $response;
});

// -------------------------
// Services: Config
// -------------------------
$di->setShared("config", function () use ($env) {
    return (object) [
        "app" => (object) [
            "env" => $env("APP_ENV", "local"),
            "debug" => (bool) $env("APP_DEBUG", "1"),
            "basePath" => dirname(__DIR__),
            "timezone" => $env("APP_TIMEZONE", "Asia/Qatar"),
        ],
        "cors" => (object) [
            // Comma-separated list of allowed origins. Use * only in dev.
            "allowedOrigins" => array_values(
                array_filter(
                    array_map(
                        "trim",
                        explode(",", (string) $env("CORS_ALLOWED_ORIGINS", "*"))
                    )
                )
            ),
            "allowedMethods" => [
                "GET",
                "POST",
                "PUT",
                "PATCH",
                "DELETE",
                "OPTIONS",
            ],
            "allowedHeaders" => [
                "Authorization",
                "Content-Type",
                "Accept",
                "X-Correlation-Id",
                "X-Idempotency-Key",
            ],
            "exposedHeaders" => ["X-Correlation-Id"],
            "maxAge" => (int) $env("CORS_MAX_AGE", "600"),
        ],
        "security" => (object) [
            "jwtSecret" => $env("JWT_SECRET", "change-me"),
            "jwtIssuer" => $env("JWT_ISSUER", "kyc-api"),
            "jwtAudience" => $env("JWT_AUDIENCE", "kyc-clients"),
            "jwtTtlSeconds" => (int) $env("JWT_TTL", "3600"),
        ],
        "db" => (object) [
            "host" => $env("DB_HOST", "127.0.0.1"),
            "port" => (int) $env("DB_PORT", "3306"),
            "name" => $env("DB_NAME", "kyc"),
            "user" => $env("DB_USER", "root"),
            "pass" => $env("DB_PASS", ""),
            "charset" => $env("DB_CHARSET", "utf8mb4"),
        ],
        "logging" => (object) [
            "channel" => $env("LOG_CHANNEL", "kyc-api"),
        ],
    ];
});

// -------------------------
// Services: Request context (correlation id, user id etc.)
// -------------------------
$di->setShared("requestContext", function () {
    return new RequestContext('');
});

// -------------------------
// Services: Permission registry
// Implemented in next canvas; here we register an empty placeholder that will be overridden.
// -------------------------
$di->setShared("permissionRegistry", function () {
    return new class {
        private array $map = [];
        public function set(string $routeName, ?string $permission): void
        {
            $this->map[$routeName] = $permission;
        }
        public function get(string $routeName): ?string
        {
            return $this->map[$routeName] ?? null;
        }
    };
});

// -------------------------
// Services: Error responder (uniform API error envelope)
// -------------------------
$di->setShared("apiResponder", function () use ($di) {
    return new class ($di) {
        public function __construct(private FactoryDefault $di)
        {
        }

        public function ok(
            $data = null,
            int $statusCode = 200,
            ?array $meta = null
        ): Response {
            /** @var Response $res */
            $res = $this->di->getShared("response");
            $ctx = $this->di->getShared("requestContext");

            $payload = [
                "correlation_id" => $ctx->correlationId,
                "success" => true,
                "data" => $data ?? (object) [],
            ];
            if ($meta !== null) {
                $payload["meta"] = $meta;
            }

            $res->setStatusCode($statusCode);
            $res->setJsonContent($payload);
            return $res;
        }

        public function error(
            string $code,
            string $message,
            int $statusCode = 400,
            array $details = []
        ): Response {
            /** @var Response $res */
            $res = $this->di->getShared("response");
            $ctx = $this->di->getShared("requestContext");

            $res->setStatusCode($statusCode);
            $res->setJsonContent([
                "correlation_id" => $ctx->correlationId,
                "success" => false,
                "error" => [
                    "code" => $code,
                    "message" => $message,
                    "details" => $details,
                ],
            ]);
            return $res;
        }
    };
});

require __DIR__ . '/services.php';

// -------------------------
// App init
// -------------------------
$config = $di->getShared("config");
@date_default_timezone_set($config->app->timezone);

$app = new Micro($di);

// -------------------------
// Global error handling (Micro)
// -------------------------
$app->error(function (\Throwable $e) use ($di, $config) {
    $responder = $di->getShared("apiResponder");

    if ($config->app->debug) {
        return $responder->error("INTERNAL_ERROR", $e->getMessage(), 500, [
            "type" => get_class($e),
            "file" => $e->getFile(),
            "line" => $e->getLine(),
        ]);
    }

    return $responder->error("INTERNAL_ERROR", "Internal server error", 500);
});

// -------------------------
// Register middlewares (implemented in next canvas)
// IMPORTANT: we register by classname strings now; once classes exist, DI can instantiate.
// -------------------------
$middlewareClasses = [
    // Correlation ID MUST be first
    "App\\Middleware\\CorrelationIdMiddleware",
    "App\\Middleware\\CorsMiddleware",
    "App\\Middleware\\RouteContextMiddleware",
    "App\\Middleware\\JsonOnlyMiddleware",
    "App\\Middleware\\AuthMiddleware",
    "App\\Middleware\\RbacMiddleware",
    "App\\Middleware\\IdempotencyMiddleware",
    "App\\Middleware\\RateLimitMiddleware",
];

foreach ($middlewareClasses as $class) {
    if (class_exists($class)) {
        $app->before(function () use ($di, $app, $class) {
            $mw = $di->has($class) ? $di->get($class) : new $class($di);
            // Convention: middleware has handle(Micro $app): bool
            return $mw->handle($app);
        });
    }
}

// -------------------------
// Load routes (single file)
// -------------------------
require dirname(__DIR__) . "/routes.php";

// -------------------------
// Not Found handler
// -------------------------
$app->notFound(function () use ($di) {
    return $di
        ->getShared("apiResponder")
        ->error("NOT_FOUND", "Route not found", 404);
});

// -------------------------
// Run
// -------------------------
$app->handle($_SERVER["REQUEST_URI"] ?? "/");
