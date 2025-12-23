<?php

namespace App\Services;

use App\Domain\Repository\RoleRepository;

final class RoleService
{
    public function __construct(private RoleRepository $repo) {}

    public function list(): array
    {
        $roles = $this->repo->listRoles();
        return [
            'items' => array_map(function ($r) {
                return [
                    'id' => (int)$r['id'],
                    'name' => (string)$r['name'],
                    'description' => $r['description'] ?? null,
                    'is_system' => (int)($r['is_system'] ?? 0),
                    'created_at' => (string)($r['created_at'] ?? ''),
                ];
            }, $roles),
        ];
    }

    public function create(array $payload): array
    {
        $name = $this->normalizeName((string)($payload['name'] ?? ''));
        $desc = $this->nullIfEmpty($payload['description'] ?? null);

        if ($name === '' || strlen($name) > 64) {
            throw new \InvalidArgumentException('Role name must be 1..64 characters');
        }
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Role name must match [a-z0-9_]+');
        }

        $id = $this->repo->insertRole($name, $desc, 0);
        return $this->get($id);
    }

    public function get(int $id): array
    {
        $role = $this->repo->findRole($id);
        if (!$role) throw new \RuntimeException('Not found');

        return [
            'id' => (int)$role['id'],
            'name' => (string)$role['name'],
            'description' => $role['description'] ?? null,
            'is_system' => (int)($role['is_system'] ?? 0),
            'created_at' => (string)($role['created_at'] ?? ''),
            'permissions' => $this->repo->listRolePermissions($id),
        ];
    }

    public function update(int $id, array $payload): array
    {
        $role = $this->repo->findRole($id);
        if (!$role) throw new \RuntimeException('Not found');
        if ((int)$role['is_system'] === 1) {
            throw new \InvalidArgumentException('System roles cannot be modified');
        }

        $name = $this->normalizeName((string)($payload['name'] ?? $role['name']));
        $desc = $this->nullIfEmpty($payload['description'] ?? ($role['description'] ?? null));

        if ($name === '' || strlen($name) > 64) {
            throw new \InvalidArgumentException('Role name must be 1..64 characters');
        }
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Role name must match [a-z0-9_]+');
        }

        $this->repo->updateRole($id, $name, $desc);
        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $role = $this->repo->findRole($id);
        if (!$role) throw new \RuntimeException('Not found');
        if ((int)$role['is_system'] === 1) {
            throw new \InvalidArgumentException('System roles cannot be deleted');
        }
        $this->repo->deleteRole($id);
    }

    public function assignPermissions(int $id, array $permNames): array
    {
        $role = $this->repo->findRole($id);
        if (!$role) throw new \RuntimeException('Not found');
        if ((int)$role['is_system'] === 1) {
            throw new \InvalidArgumentException('System roles cannot be modified');
        }

        $permNames = array_values(array_unique(array_filter(array_map('strval', $permNames))));
        if (!$permNames) {
            throw new \InvalidArgumentException('permissions must be a non-empty array');
        }

        $permIds = $this->repo->findPermissionIdsByNames($permNames);
        if (count($permIds) !== count($permNames)) {
            throw new \InvalidArgumentException('One or more permissions are invalid');
        }

        $this->repo->replaceRolePermissions($id, $permIds);
        return $this->get($id);
    }

    private function normalizeName(string $s): string
    {
        return trim(mb_strtolower($s));
    }

    private function nullIfEmpty($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }
}