<?php
namespace App\Domain\Repository;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

final class PermissionRepository
{
    public function __construct(private AbstractPdo $db) {}

    public function listPermissions(?string $q): array
    {
        $params = [];
        $where = '';
        if ($q !== null && trim($q) !== '') {
            $where = 'WHERE name LIKE :q';
            $params['q'] = '%' . trim($q) . '%';
        }

        return $this->db->fetchAll(
            "SELECT id, name, description, created_at
             FROM permissions
             $where
             ORDER BY name ASC",
            DbEnum::FETCH_ASSOC,
            $params
        );
    }
}