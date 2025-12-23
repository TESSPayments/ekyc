<?php
namespace App\Services;

use App\Domain\Repository\AuthRepository;

final class AuthService
{
    public function __construct(
        private AuthRepository $repo,
        private JwtService $jwt,
        private RbacService $rbac
    ) {
    }

    /**
     * @return array{access_token:string,expires_in:int,refresh_token:string,user:array}
     */
    public function login(
        string $email,
        string $password,
        string $correlationId,
        ?string $ip,
        ?string $ua
    ): array {
        $email = trim(mb_strtolower($email));
        if ($email === "" || $password === "") {
            throw new \InvalidArgumentException(
                "Email and password are required"
            );
        }

        $user = $this->repo->findUserByEmail($email);
        if (!$user || ($user["status"] ?? "") !== "active") {
            throw new \RuntimeException("Invalid credentials");
        }

        $hash = (string) ($user["password_hash"] ?? "");
        if ($hash === "" || !password_verify($password, $hash)) {
            throw new \RuntimeException("Invalid credentials");
        }

        $userId = (int) $user["id"];
        $roles = $this->rbac->getUserRoles($userId);

        $accessToken = $this->jwt->issueToken($userId, $roles);

        $refreshToken = $this->generateRefreshToken();
        $refreshHash = hash("sha256", $refreshToken);
        $expiresAt = (new \DateTimeImmutable("now", new \DateTimeZone("UTC")))
            ->modify("+30 days")
            ->format("Y-m-d H:i:s");
        $this->repo->insertRefreshToken(
            $userId,
            $refreshHash,
            $expiresAt,
            $correlationId,
            $ip,
            $ua
        );

        return [
            "access_token" => $accessToken,
            "expires_in" => $this->getAccessTtlSeconds(),
            "refresh_token" => $refreshToken,
            "user" => [
                "id" => $userId,
                "email" => (string) $user["email"],
                "roles" => $roles,
            ],
        ];
    }

    /**
     * Refreshes access token; rotates refresh token.
     *
     * @return array{access_token:string,expires_in:int,refresh_token:string}
     */
    public function refresh(
        string $refreshToken,
        string $correlationId,
        ?string $ip,
        ?string $ua
    ): array {
        $refreshToken = trim($refreshToken);
        if ($refreshToken === "") {
            throw new \InvalidArgumentException("refresh_token is required");
        }

        $hash = hash("sha256", $refreshToken);
        $row = $this->repo->findValidRefreshToken($hash);
        if (!$row) {
            throw new \RuntimeException("Invalid refresh token");
        }

        $this->repo->touchRefreshToken((int) $row["id"]);

        $userId = (int) $row["user_id"];
        $user = $this->repo->findUserById($userId);
        if (!$user || ($user["status"] ?? "") !== "active") {
            // revoke token if user disabled
            $this->repo->revokeRefreshTokenByHash($hash);
            throw new \RuntimeException("User not active");
        }

        // Rotate refresh token
        $this->repo->revokeRefreshTokenByHash($hash);

        $newRefreshToken = $this->generateRefreshToken();
        $newHash = hash("sha256", $newRefreshToken);
        $expiresAt = (new \DateTimeImmutable("now", new \DateTimeZone("UTC")))
            ->modify("+30 days")
            ->format("Y-m-d H:i:s");
        $this->repo->insertRefreshToken(
            $userId,
            $newHash,
            $expiresAt,
            $correlationId,
            $ip,
            $ua
        );

        $roles = $this->rbac->getUserRoles($userId);
        $accessToken = $this->jwt->issueToken($userId, $roles);

        return [
            "access_token" => $accessToken,
            "expires_in" => $this->getAccessTtlSeconds(),
            "refresh_token" => $newRefreshToken,
        ];
    }

    /**
     * Logout by revoking a refresh token.
     */
    public function logout(string $refreshToken): void
    {
        $refreshToken = trim($refreshToken);
        if ($refreshToken === "") {
            throw new \InvalidArgumentException("refresh_token is required");
        }
        $hash = hash("sha256", $refreshToken);
        $this->repo->revokeRefreshTokenByHash($hash);
    }

    /**
     * Returns user profile with roles/permissions.
     */
    public function me(int $userId): array
    {
        $user = $this->repo->findUserById($userId);
        if (!$user) {
            throw new \RuntimeException("User not found");
        }

        $roles = $this->rbac->getUserRoles($userId);
        $perms = $this->rbac->getUserPermissions($userId);

        return [
            "id" => (int) $user["id"],
            "email" => (string) $user["email"],
            "status" => (string) $user["status"],
            "roles" => $roles,
            "permissions" => $perms,
        ];
    }

    private function generateRefreshToken(): string
    {
        // 256-bit random -> base64url
        $raw = random_bytes(32);
        return rtrim(strtr(base64_encode($raw), "+/", "-_"), "=");
    }

    private function getAccessTtlSeconds(): int
    {
        // JwtService doesn't expose TTL, but config does; keep stable by reading env if needed.
        // Preferred: inject TTL into AuthService; for now keep aligned with default in bootstrap.
        return (int) (getenv("JWT_TTL") ?: 3600);
    }
}
