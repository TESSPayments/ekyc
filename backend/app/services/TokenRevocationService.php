<?php
namespace App\Services;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Db\Enum as DbEnum;

final class TokenRevocationService
{
    public function __construct(private AbstractPdo $db) {}

    /**
     * Mark a token JTI as revoked until expires_at.
     */
    public function revoke(string $jti, int $userId, string $tokenType, int $expUnix, ?string $reason, ?string $correlationId): void
    {
        $jti = trim($jti);
        if ($jti === '') {
            return;
        }
        if (!in_array($tokenType, ['access','refresh'], true)) {
            $tokenType = 'access';
        }

        // Convert exp -> UTC datetime
        $expUnix = max(time(), $expUnix);
        $expiresAt = (new \DateTimeImmutable('@' . $expUnix))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO revoked_tokens (jti, user_id, token_type, expires_at, reason, correlation_id)
             VALUES (:j, :u, :t, :e, :r, :c)
             ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), reason = VALUES(reason), correlation_id = VALUES(correlation_id)',
            [
                'j' => $jti,
                'u' => $userId,
                't' => $tokenType,
                'e' => $expiresAt,
                'r' => $reason,
                'c' => $correlationId,
            ]
        );
    }

    /**
     * True if token JTI is revoked and not expired.
     */
    public function isRevoked(string $jti): bool
    {
        $jti = trim($jti);
        if ($jti === '') {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT 1 AS ok FROM revoked_tokens WHERE jti = :j AND expires_at > NOW() LIMIT 1',
            DbEnum::FETCH_ASSOC,
            ['j' => $jti]
        );

        return (bool)($row['ok'] ?? false);
    }

    /**
     * Optional cleanup task (cron/command): delete expired revocations.
     */
    public function purgeExpired(): int
    {
        $this->db->execute('DELETE FROM revoked_tokens WHERE expires_at <= NOW()');
        return (int)$this->db->affectedRows();
    }
}
