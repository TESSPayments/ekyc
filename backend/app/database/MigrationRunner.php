<?php
namespace App\Database;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;

final class MigrationRunner
{
    public function __construct(
        private AbstractPdo $db,
        private string $rootPath
    ) {
    }

    public function migrate(): void
    {
        $this->ensureMigrationsTable();

        $dir = $this->rootPath . "/app/Database/Migrations";
        $files = glob($dir . "/*.php") ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if ($this->isApplied($name)) {
                continue;
            }

            $sql = $this->loadSqlFromFile($file);
            $this->applySql($sql, $name);
        }
    }

    /**
     * Drops known tables to rebuild schema (development convenience).
     */
    public function fresh(): void
    {
        // Drop in reverse dependency order
        $tables = [
            "idempotency_keys",
            "notification_deliveries",
            "notifications",
            "audit_logs",
            "merchants",
            "risk_scores",
            "aml_matches",
            "aml_runs",
            "document_files",
            "documents",
            "application_parties",
            "workflow_transitions",
            "workflow_states",
            "applications",
            "role_permissions",
            "user_roles",
            "permissions",
            "roles",
            "users",
            "schema_migrations",
        ];

        foreach ($tables as $t) {
            try {
                $this->db->execute("DROP TABLE IF EXISTS " . $t);
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(255) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_schema_migrations (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function isApplied(string $migration): bool
    {
        $row = $this->db->fetchOne(
            "SELECT 1 FROM schema_migrations WHERE migration = :m LIMIT 1",
            \Phalcon\Db\Enum::FETCH_ASSOC,
            ["m" => $migration]
        );
        return is_array($row) && !empty($row);
    }

    private function loadSqlFromFile(string $file): string
    {
        $sql = (string) require $file;
        if (trim($sql) === "") {
            throw new \RuntimeException(
                "Migration returned empty SQL: " . basename($file)
            );
        }
        return $sql;
    }

    private function applySql(string $sql, string $migrationName): void
    {
        // IMPORTANT:
        // MySQL DDL statements (CREATE/ALTER/DROP TABLE) can implicitly commit.
        // Wrapping such migrations in a transaction can lead to "There is no active transaction"
        // on rollback/commit depending on driver behavior. We therefore run migrations WITHOUT
        // explicit begin/commit and only guard rollback where applicable.

        try {
            foreach ($this->splitStatements($sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== "") {
                    $this->db->execute($stmt);
                }
            }

            $this->db->execute(
                "INSERT INTO schema_migrations (migration) VALUES (:m)",
                ["m" => $migrationName]
            );
        } catch (\Throwable $e) {
            // Safety: rollback only if a transaction is actually active
            try {
                if (
                    method_exists($this->db, "isUnderTransaction") &&
                    $this->db->isUnderTransaction()
                ) {
                    $this->db->rollback();
                }
            } catch (\Throwable) {
                // ignore rollback failures
            }
            throw $e;
        }
    }

    /**
     * Naive SQL splitter by semicolon, safe enough for our migrations.
     * Avoid using semicolons inside strings.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $parts = explode(";", $sql);
        $stmts = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== "") {
                $stmts[] = $p;
            }
        }
        return $stmts;
    }
}
