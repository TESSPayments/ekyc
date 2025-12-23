<?php
namespace App\Infrastructure;

use Phalcon\Db\Adapter\Pdo\Mysql;

final class DbFactory
{
    public static function mysql(object $dbConfig): Mysql
    {
        $adapter = new Mysql([
            "host" => (string) $dbConfig->host,
            "username" => (string) $dbConfig->user,
            "password" => (string) $dbConfig->pass,
            "dbname" => (string) $dbConfig->name,
            "port" => (int) $dbConfig->port,
            "charset" => (string) $dbConfig->charset,
            "options" => [
                // PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, (Phalcon manages errors but this is useful)
                3 => 2, // ERRMODE => EXCEPTION
                // PDO::ATTR_EMULATE_PREPARES => false
                20 => false,
            ],
        ]);

        // Ensure strict SQL mode where possible
        try {
            $adapter->execute(
                "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
            );
            $adapter->execute(
                "SET NAMES " . self::escapeCharset((string) $dbConfig->charset)
            );
        } catch (\Throwable) {
            // ignore session setup failure
        }

        return $adapter;
    }

    private static function escapeCharset(string $charset): string
    {
        // minimal sanitization for SET NAMES
        return preg_replace("/[^a-zA-Z0-9_]/", "", $charset) ?: "utf8mb4";
    }
}
