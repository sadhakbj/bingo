<?php

declare(strict_types=1);

namespace App\Providers;

use Bingo\Attributes\Provider\ServiceProvider;

#[ServiceProvider]
class AppServiceProvider
{
    // Interface → concrete bindings go on the concrete class via #[Binds].
    // Use #[Singleton] here for third-party classes, config-driven construction,
    // or anything that needs manual wiring.
}