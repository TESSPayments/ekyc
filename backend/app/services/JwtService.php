<?php
namespace App\Services;

/**
 * Minimal HS256 JWT implementation.
 *
 * This avoids external dependencies. Supports:
 * - issueToken(sub, roles, extraClaims)
 * - validateToken(jwt) -> payload array
 */
final class JwtService
{
    public function __construct(
        private string $secret,
        private string $issuer,
        private string $audience,
        private int $ttlSeconds = 3600
    ) {
        if (strlen($this->secret) < 16) {
            // Enforce a reasonable shared secret length
            throw new \InvalidArgumentException(
                "JWT secret is too short; use at least 16 characters."
            );
        }
    }

    /**
     * @param int $sub User id
     * @param array $roles e.g. ['sales','sales_manager']
     * @param array $extraClaims additional claims
     */
    public function issueToken(
        int $sub,
        array $roles = [],
        array $extraClaims = []
    ): string {
        $now = time();
        $payload = array_merge(
            [
                "iss" => $this->issuer,
                "aud" => $this->audience,
                "iat" => $now,
                "nbf" => $now,
                "exp" => $now + $this->ttlSeconds,
                "sub" => (string) $sub,
                "roles" => array_values($roles),
                "jti" => bin2hex(random_bytes(16))
            ],
            $extraClaims
        );

        $header = ["alg" => "HS256", "typ" => "JWT"];

        $h = self::b64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = self::b64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = self::b64UrlEncode(
            hash_hmac("sha256", $h . "." . $p, $this->secret, true)
        );

        return $h . "." . $p . "." . $sig;
    }

    /**
     * @return array<string,mixed>
     */
    public function validateToken(string $jwt): array
    {
        $parts = explode(".", $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException("Invalid token format");
        }

        [$h, $p, $s] = $parts;

        $header = json_decode(self::b64UrlDecode($h), true);
        if (!is_array($header) || ($header["alg"] ?? "") !== "HS256") {
            throw new \RuntimeException("Unsupported token algorithm");
        }

        $payload = json_decode(self::b64UrlDecode($p), true);
        if (!is_array($payload)) {
            throw new \RuntimeException("Invalid token payload");
        }

        $expected = self::b64UrlEncode(
            hash_hmac("sha256", $h . "." . $p, $this->secret, true)
        );
        if (!hash_equals($expected, $s)) {
            throw new \RuntimeException("Invalid token signature");
        }

        $now = time();

        if (($payload["iss"] ?? null) !== $this->issuer) {
            throw new \RuntimeException("Invalid token issuer");
        }
        if (($payload["aud"] ?? null) !== $this->audience) {
            throw new \RuntimeException("Invalid token audience");
        }
        if (
            isset($payload["nbf"]) &&
            is_numeric($payload["nbf"]) &&
            $now < (int) $payload["nbf"]
        ) {
            throw new \RuntimeException("Token not yet valid");
        }
        if (
            !isset($payload["exp"]) ||
            !is_numeric($payload["exp"]) ||
            $now >= (int) $payload["exp"]
        ) {
            throw new \RuntimeException("Token expired");
        }

        // Normalize sub to int where possible
        if (isset($payload["sub"])) {
            $payload["sub"] = (int) $payload["sub"];
        }

        return $payload;
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    private static function b64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat("=", 4 - $pad);
        }
        return base64_decode(strtr($data, "-_", "+/")) ?: "";
    }
}
