<?php
namespace App\Services;

final class ApiVersion
{
    public const V1 = 'v1';

    /**
     * Prefix path for the API version.
     *
     * Examples:
     *  - ApiVersion::prefix(ApiVersion::V1) => '/api/v1'
     */
    public static function prefix(string $version): string
    {
        $version = trim($version);
        if (!preg_match('/^v[0-9]+$/', $version)) {
            throw new \InvalidArgumentException('Invalid API version format (expected vN)');
        }
        return '/api/' . $version;
    }

    public static function path(string $version, string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return self::prefix($version) . $path;
    }
}
