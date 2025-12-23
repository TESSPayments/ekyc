<?php
namespace App\Domain\Repository;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

final class UserRepository
{
    public function __construct(private AbstractPdo $db) {}

    public function listUsers(int $limit, int $offset, ?string $q): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $params = ['limit' => $limit, 'offset' => $offset];
        $where = '';

        if ($q !== null && trim($q) !== '') {
            $where = 'WHERE u.email LIKE :q OR up.full_name LIKE :q';
            $params['q'] = '%' . trim($q) . '%';
        }

        $rows = $this->db->fetchAll(
            "SELECT u.id, u.email, u.status, u.created_at,
                    up.full_name, up.phone, up.department
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             $where
             ORDER BY u.id DESC
             LIMIT :limit OFFSET :offset",
            DbEnum::FETCH_ASSOC,
            $params
        );

        $totalRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS c
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             $where",
            DbEnum::FETCH_ASSOC,
            array_diff_key($params, ['limit' => 1, 'offset' => 1])
        );

        return [
            'rows' => $rows,
            'total' => (int)($totalRow['c'] ?? 0),
        ];
    }

    public function findUser(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT u.id, u.email, u.status, u.created_at,
                    up.full_name, up.phone, up.department, up.notes
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.id = :id
             LIMIT 1",
            DbEnum::FETCH_ASSOC,
            ['id' => $id]
        );
        return is_array($row) && $row ? $row : null;
    }

    public function insertUser(string $email, string $passwordHash): int
    {
        $this->db->execute(
            'INSERT INTO users (email, password_hash, status) VALUES (:e, :h, :s)',
            ['e' => $email, 'h' => $passwordHash, 's' => 'active']
        );
        return (int)$this->db->lastInsertId();
    }

    public function upsertProfile(int $userId, ?string $fullName, ?string $phone, ?string $department, ?string $notes): void
    {
        $this->db->execute(
            'INSERT INTO user_profiles (user_id, full_name, phone, department, notes)
             VALUES (:u, :f, :p, :d, :n)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                phone = VALUES(phone),
                department = VALUES(department),
                notes = VALUES(notes)',
            ['u' => $userId, 'f' => $fullName, 'p' => $phone, 'd' => $department, 'n' => $notes]
        );
    }

    public function updateUserEmail(int $userId, string $email): void
    {
        $this->db->execute('UPDATE users SET email = :e WHERE id = :id', ['e' => $email, 'id' => $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->db->execute('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => $passwordHash, 'id' => $userId]);
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->execute('UPDATE users SET status = :s WHERE id = :id', ['s' => $status, 'id' => $userId]);
    }

    public function findRoleIdsByNames(array $roleNames): array
    {
        if (!$roleNames) return [];
        $in = implode(',', array_fill(0, count($roleNames), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id FROM roles WHERE name IN ($in)",
            DbEnum::FETCH_ASSOC,
            array_values($roleNames)
        );
        return array_map(static fn($r) => (int)$r['id'], $rows);
    }

    public function replaceUserRoles(int $userId, array $roleIds): void
    {
        $this->db->execute('DELETE FROM user_roles WHERE user_id = :u', ['u' => $userId]);

        foreach ($roleIds as $rid) {
            $this->db->execute(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)',
                ['u' => $userId, 'r' => (int)$rid]
            );
        }
    }

    public function listUserRoles(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.name FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :u',
            DbEnum::FETCH_ASSOC,
            ['u' => $userId]
        );
        return array_values(array_filter(array_map(static fn($r) => (string)($r['name'] ?? ''), $rows)));
    }
}