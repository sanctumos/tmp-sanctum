<?php

declare(strict_types=1);

namespace SanctumTmp;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(Config $config): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $dir = dirname($config->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $config->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schemaFile = dirname(__DIR__) . '/db/schema.sql';
        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new \RuntimeException('Missing schema: ' . $schemaFile);
        }
        $pdo->exec($sql);
        self::$pdo = $pdo;
        return $pdo;
    }

    /** @internal test helper */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
