<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Attributes\Provider\Singleton;
use Config\AppConfig;

#[ServiceProvider]
class AppConfigServiceProvider
{
    #[Singleton]
    public function appConfig(AppConfig $config): AppConfig
    {
        return $config;
    }
}
