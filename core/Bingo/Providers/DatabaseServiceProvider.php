<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Application;
use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Attributes\Provider\Singleton;
use Bingo\Config\DatabaseConfig;
use Bingo\Database\Database;
use Config\DbConfig;

#[ServiceProvider]
class DatabaseServiceProvider
{
    /**
     * @throws \ReflectionException
     */
    #[Singleton]
    public function databaseConfig(Application $app, DbConfig $dbConfig): DatabaseConfig
    {
        $defaultName = $dbConfig->default;

        $connections = [];
        foreach ($dbConfig->connections as $name => $driverClass) {
            $connections[$name] = $app->make($driverClass);
        }

        if (!isset($connections[$defaultName])) {
            throw new \InvalidArgumentException(
                "DB_CONNECTION is set to '{$defaultName}' but it is not listed in DbConfig::\$connections."
            );
        }

        return new DatabaseConfig($defaultName, $connections);
    }

    public function boot(DatabaseConfig $config): void
    {
        Database::setup($config);
    }
}
