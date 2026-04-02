<?php

declare(strict_types=1);

namespace Bingo\Attributes\Provider;

use Attribute;

/**
 * Marks a provider method as a boot action.
 * Runs after all #[Singleton] registrations are complete.
 * Method parameters are resolved from the container.
 * Return value is ignored.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Boots {}