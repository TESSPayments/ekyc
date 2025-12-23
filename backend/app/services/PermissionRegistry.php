<?php
namespace App\Services;

/**
 * Route-name to required-permission registry.
 *
 * Populated in app/routes.php during route binding.
 */
final class PermissionRegistry
{
    /** @var array<string, string|null> */
    private array $map = [];

    public function set(string $routeName, ?string $permission): void
    {
        $this->map[$routeName] = $permission;
    }

    public function get(string $routeName): ?string
    {
        return $this->map[$routeName] ?? null;
    }

    /**
     * Useful for debugging/ops.
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->map;
    }
}
