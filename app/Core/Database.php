<?php

namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {

            $config = require __DIR__ . '/../Config/database.php';

            self::$pdo = new PDO(
                $config['dsn'],
                $config['user'],
                $config['password']
            );

            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$pdo;
    }
}
