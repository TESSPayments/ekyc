<?php
namespace App\Domain\Repository;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

final class RoleRepository
{
    public function __construct(private AbstractPdo $db) {}

    public function listRoles(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, description, is_system, created_at FROM roles ORDER BY is_system DESC, name ASC',
            DbEnum::FETCH_ASSOC
        );
    }

    public function findRole(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, name, description, is_system, created_at FROM roles WHERE id = :id LIMIT 1',
            DbEnum::FETCH_ASSOC,
            ['id' => $id]
        );
        return is_array($row) && $row ? $row : null;
    }

    public function insertRole(string $name, ?string $description, int $isSystem): int
    {
        $this->db->execute(
            'INSERT INTO roles (name, description, is_system) VALUES (:n, :d, :s)',
            ['n' => $name, 'd' => $description, 's' => $isSystem]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateRole(int $id, string $name, ?string $description): void
    {
        $this->db->execute(
            'UPDATE roles SET name = :n, description = :d WHERE id = :id',
            ['n' => $name, 'd' => $description, 'id' => $id]
        );
    }

    public function deleteRole(int $id): void
    {
        // delete dependencies first
        $this->db->execute('DELETE FROM role_permissions WHERE role_id = :id', ['id' => $id]);
        $this->db->execute('DELETE FROM user_roles WHERE role_id = :id', ['id' => $id]);
        $this->db->execute('DELETE FROM roles WHERE id = :id', ['id' => $id]);
    }

    public function listRolePermissions(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.name
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :r
             ORDER BY p.name ASC',
            DbEnum::FETCH_ASSOC,
            ['r' => $roleId]
        );
        return array_values(array_filter(array_map(static fn($r) => (string)($r['name'] ?? ''), $rows)));
    }

    public function findPermissionIdsByNames(array $permNames): array
    {
        if (!$permNames) return [];
        $in = implode(',', array_fill(0, count($permNames), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id FROM permissions WHERE name IN ($in)",
            DbEnum::FETCH_ASSOC,
            array_values($permNames)
        );
        return array_map(static fn($r) => (int)$r['id'], $rows);
    }

    public function replaceRolePermissions(int $roleId, array $permissionIds): void
    {
        $this->db->execute('DELETE FROM role_permissions WHERE role_id = :r', ['r' => $roleId]);
        foreach ($permissionIds as $pid) {
            $this->db->execute(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:r, :p)',
                ['r' => $roleId, 'p' => (int)$pid]
            );
        }
    }
}