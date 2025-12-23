<?php
namespace App\Services;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

/**
 * RBAC checks backed by DB.
 */
final class RbacService
{
    public function __construct(private AbstractPdo $db)
    {
    }

    /**
     * Checks if user has a permission through roles.
     */
    public function userHasPermission(int $userId, string $permissionName): bool
    {
        if ($userId <= 0 || $permissionName === "") {
            return false;
        }

        $sql = "
            SELECT 1
            FROM user_roles ur
            INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :uid
            AND p.name = :perm
            LIMIT 1
            ";

        $row = $this->db->fetchOne($sql, DbEnum::FETCH_ASSOC, [
            "uid" => $userId,
            "perm" => $permissionName,
        ]);

        return is_array($row) && !empty($row);
    }

    /**
     * Returns user's roles (names).
     * @return string[]
     */
    public function getUserRoles(int $userId): array
    {
        $sql = "
            SELECT r.name
            FROM user_roles ur
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
            ";

        $rows = $this->db->fetchAll($sql, DbEnum::FETCH_ASSOC, [
            "uid" => $userId,
        ]);
        return array_values(
            array_filter(
                array_map(static fn($r) => (string) ($r["name"] ?? ""), $rows)
            )
        );
    }

    /**
     * Returns user's permissions (names) - useful for caching.
     * @return string[]
     */
    public function getUserPermissions(int $userId): array
    {
        $sql = "
            SELECT DISTINCT p.name
            FROM user_roles ur
            INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :uid
            ";

        $rows = $this->db->fetchAll($sql, DbEnum::FETCH_ASSOC, [
            "uid" => $userId,
        ]);
        return array_values(
            array_filter(
                array_map(static fn($r) => (string) ($r["name"] ?? ""), $rows)
            )
        );
    }
}
