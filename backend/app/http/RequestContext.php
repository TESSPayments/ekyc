<?php
namespace App\Http;

/**
 * Request-scoped context container.
 *
 * Lives for the lifetime of a single HTTP request.
 * Populated progressively by middleware.
 */
final class RequestContext
{
    /** Correlation ID (always present) */
    public string $correlationId;

    /** Authenticated user ID (null if unauthenticated) */
    public ?int $userId = null;

    /** Roles from JWT payload */
    public array $roles = [];

    /** Fully qualified route name (e.g. v1.admin.users.update) */
    public ?string $routeName = null;

    /** Optional: client / tenant later */
    public ?string $tenantId = null;

    public function __construct(string $correlationId)
    {
        $this->correlationId = $correlationId;
    }
}
