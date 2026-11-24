<?php

declare(strict_types=1);

namespace FormularioDorado\Persistence;

use FormularioDorado\Config\AppConfig;
use PDO;
use Pdo\Mysql;

final class DatabaseConnection
{
    public static function open(AppConfig $config): PDO
    {
        return new PDO($config->databaseDsn, $config->databaseUser, $config->databasePassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            Mysql::ATTR_MULTI_STATEMENTS => false,
        ]);
    }
}
