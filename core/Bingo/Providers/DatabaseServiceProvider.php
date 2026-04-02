<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Attributes\Provider\Boots;
use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Attributes\Provider\Singleton;
use Bingo\Config\ConfigLoader;
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
    public function databaseConfig(): DatabaseConfig
    {
        /** @var DbConfig $dbConfig */
        $dbConfig    = ConfigLoader::load(DbConfig::class);
        $defaultName = $dbConfig->default;

        $connections = [];
        foreach ($dbConfig->connections as $name => $driverClass) {
            $connections[$name] = ConfigLoader::load($driverClass);
        }

        if (!isset($connections[$defaultName])) {
            throw new \InvalidArgumentException(
                "DB_CONNECTION is set to '{$defaultName}' but it is not listed in DbConfig::\$connections."
            );
        }

        return new DatabaseConfig($defaultName, $connections);
    }

    #[Boots]
    public function boot(DatabaseConfig $config): void
    {
        Database::setup($config);
    }
}
