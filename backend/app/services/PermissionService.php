<?php

namespace App\Services;

use App\Domain\Repository\PermissionRepository;

final class PermissionService
{
    public function __construct(private PermissionRepository $repo) {}

    public function list(?string $q): array
    {
        $rows = $this->repo->listPermissions($q);
        return [
            'items' => array_map(static function ($r) {
                return [
                    'id' => (int)$r['id'],
                    'name' => (string)$r['name'],
                    'description' => $r['description'] ?? null,
                    'created_at' => (string)($r['created_at'] ?? ''),
                ];
            }, $rows),
        ];
    }
}