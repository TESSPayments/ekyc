<?php
namespace App\Services;

use App\Domain\Repository\UserRepository;

final class UserService
{
    public function __construct(private UserRepository $repo) {}

    public function list(int $page, int $pageSize, ?string $q): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));

        $offset = ($page - 1) * $pageSize;
        $data = $this->repo->listUsers($pageSize, $offset, $q);

        return [
            'items' => array_map([$this, 'mapUserRow'], $data['rows']),
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $data['total'],
        ];
    }

    public function create(array $payload): array
    {
        $email = trim(mb_strtolower((string)($payload['email'] ?? '')));
        $password = (string)($payload['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email');
        }
        if (strlen($password) < 10) {
            throw new \InvalidArgumentException('Password must be at least 10 characters');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $userId = $this->repo->insertUser($email, $hash);

        $this->repo->upsertProfile(
            userId: $userId,
            fullName: $this->nullIfEmpty($payload['full_name'] ?? null),
            phone: $this->nullIfEmpty($payload['phone'] ?? null),
            department: $this->nullIfEmpty($payload['department'] ?? null),
            notes: $this->nullIfEmpty($payload['notes'] ?? null),
        );

        return $this->get($userId);
    }

    public function get(int $id): array
    {
        $row = $this->repo->findUser($id);
        if (!$row) {
            throw new \RuntimeException('Not found');
        }
        $user = $this->mapUserRow($row);
        $user['roles'] = $this->repo->listUserRoles($id);
        return $user;
    }

    public function update(int $id, array $payload): array
    {
        $row = $this->repo->findUser($id);
        if (!$row) {
            throw new \RuntimeException('Not found');
        }

        if (array_key_exists('email', $payload)) {
            $email = trim(mb_strtolower((string)$payload['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email');
            }
            $this->repo->updateUserEmail($id, $email);
        }

        if (array_key_exists('password', $payload) && (string)$payload['password'] !== '') {
            $password = (string)$payload['password'];
            if (strlen($password) < 10) {
                throw new \InvalidArgumentException('Password must be at least 10 characters');
            }
            $this->repo->updatePassword($id, password_hash($password, PASSWORD_BCRYPT));
        }

        $this->repo->upsertProfile(
            userId: $id,
            fullName: $this->nullIfEmpty($payload['full_name'] ?? null),
            phone: $this->nullIfEmpty($payload['phone'] ?? null),
            department: $this->nullIfEmpty($payload['department'] ?? null),
            notes: $this->nullIfEmpty($payload['notes'] ?? null),
        );

        return $this->get($id);
    }

    public function disable(int $id): void
    {
        $row = $this->repo->findUser($id);
        if (!$row) {
            throw new \RuntimeException('Not found');
        }
        $this->repo->setStatus($id, 'disabled');
    }

    public function assignRoles(int $id, array $roleNames): array
    {
        $roleNames = array_values(array_unique(array_filter(array_map('strval', $roleNames))));
        if (!$roleNames) {
            throw new \InvalidArgumentException('roles must be a non-empty array');
        }

        $roleIds = $this->repo->findRoleIdsByNames($roleNames);
        if (count($roleIds) !== count($roleNames)) {
            throw new \InvalidArgumentException('One or more roles are invalid');
        }

        $this->repo->replaceUserRoles($id, $roleIds);
        return $this->get($id);
    }

    private function mapUserRow(array $r): array
    {
        return [
            'id' => (int)($r['id'] ?? 0),
            'email' => (string)($r['email'] ?? ''),
            'status' => (string)($r['status'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
            'full_name' => $r['full_name'] ?? null,
            'phone' => $r['phone'] ?? null,
            'department' => $r['department'] ?? null,
            'notes' => $r['notes'] ?? null,
        ];
    }

    private function nullIfEmpty($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }
}