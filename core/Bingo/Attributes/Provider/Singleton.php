<?php

declare(strict_types=1);

namespace Bingo\Attributes\Provider;

use Attribute;

/**
 * Marks a provider method as a singleton registration.
 * The method's return type becomes the container binding key.
 * Method parameters are resolved from the container.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Singleton {}
