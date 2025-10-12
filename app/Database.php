<?php

namespace App;

use PDO;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require __DIR__ . '/../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['database'],
            $config['charset']
        );

        $options = $config['options'] ?? [];
        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                // Create database, then reconnect
                $serverDsn = sprintf('mysql:host=%s;charset=%s', $config['host'], $config['charset']);
                $serverPdo = new PDO($serverDsn, $config['username'], $config['password'], $options);
                $dbName = $config['database'];
                $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$config['charset']} COLLATE {$config['collation']}");
                self::$connection = new PDO($dsn, $config['username'], $config['password'], $options);
            } else {
                throw $e;
            }
        }
        return self::$connection;
    }
}


