<?php

namespace App\Domain\Repository;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

final class AuthRepository
{
    public function __construct(private AbstractPdo $db)
    {
    }

    public function findUserByEmail(string $email): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, email, password_hash, status FROM users WHERE email = :e LIMIT 1",
            DbEnum::FETCH_ASSOC,
            ["e" => $email]
        );
        return is_array($row) && $row ? $row : null;
    }

    public function findUserById(int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, email, status FROM users WHERE id = :id LIMIT 1",
            DbEnum::FETCH_ASSOC,
            ["id" => $userId]
        );
        return is_array($row) && $row ? $row : null;
    }

    public function insertRefreshToken(
        int $userId,
        string $tokenHash,
        string $expiresAt,
        string $correlationId,
        ?string $ip,
        ?string $userAgent
    ): void {
        $this->db->execute(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at, ip_address, user_agent, correlation_id)
VALUES (:u, :h, :e, :ip, :ua, :c)',
            [
                "u" => $userId,
                "h" => $tokenHash,
                "e" => $expiresAt,
                "ip" => $ip,
                "ua" => $userAgent,
                "c" => $correlationId,
            ]
        );
    }

    public function findValidRefreshToken(string $tokenHash): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, user_id, token_hash, expires_at, revoked_at FROM refresh_tokens
WHERE token_hash = :h AND revoked_at IS NULL AND expires_at > NOW()
LIMIT 1',
            DbEnum::FETCH_ASSOC,
            ["h" => $tokenHash]
        );
        return is_array($row) && $row ? $row : null;
    }

    public function touchRefreshToken(int $id): void
    {
        $this->db->execute(
            "UPDATE refresh_tokens SET last_used_at = NOW() WHERE id = :id",
            ["id" => $id]
        );
    }

    public function revokeRefreshTokenByHash(string $tokenHash): void
    {
        $this->db->execute(
            "UPDATE refresh_tokens SET revoked_at = NOW() WHERE token_hash = :h AND revoked_at IS NULL",
            ["h" => $tokenHash]
        );
    }

    public function revokeAllRefreshTokensForUser(int $userId): void
    {
        $this->db->execute(
            "UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = :u AND revoked_at IS NULL",
            ["u" => $userId]
        );
    }
}
