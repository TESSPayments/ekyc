<?php
namespace App\Database;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;

final class SeedRunner
{
    public function __construct(
        private AbstractPdo $db,
        private string $rootPath
    ) {}

    public function seed(): void
    {
        $dir = $this->rootPath . '/app/Database/Seeds';
        $files = glob($dir . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $callable = require $file;
            if (!is_callable($callable)) {
                throw new \RuntimeException('Seed file must return a callable: ' . basename($file));
            }

            // Seeders may include DDL in some projects; guard rollback.
            $this->db->begin();
            try {
                $callable($this->db);
                $this->db->commit();
            } catch (\Throwable $e) {
                try {
                    if (method_exists($this->db, 'isUnderTransaction') && $this->db->isUnderTransaction()) {
                        $this->db->rollback();
                    }
                } catch (\Throwable) {
                    // ignore
                }
                throw $e;
            }
        }
    }
}