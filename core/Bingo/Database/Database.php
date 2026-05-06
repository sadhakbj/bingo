<?php

declare(strict_types = 1);

namespace Bingo\Database;

use Bingo\Config\DatabaseConfig;
use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    private static ?Capsule $instance = null;

    public static function setup(DatabaseConfig $config): Capsule
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new Capsule();

        foreach ($config->all() as $name => $connection) {
            self::$instance->addConnection($connection->toArray(), $name);
        }

        self::$instance->getDatabaseManager()->setDefaultConnection($config->defaultName());
        self::$instance->setAsGlobal();
        self::$instance->bootEloquent();

        return self::$instance;
    }
}
