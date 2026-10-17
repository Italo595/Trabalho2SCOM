<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Conexao unica (singleton) com o MySQL via PDO.
 * Sempre usar prepared statements nas camadas Repository.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(array $cfg): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $cfg['host'], $cfg['port'], $cfg['name']
            );
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements reais
            ]);
        }
        return self::$pdo;
    }
}
