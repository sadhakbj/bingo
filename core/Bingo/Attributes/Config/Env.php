<?php

declare(strict_types = 1);

namespace Bingo\Attributes\Config;

use Attribute;

/**
 * Bind a constructor parameter (or property) to an environment variable.
 *
 * Usage on a promoted constructor parameter:
 *
 *   public function __construct(
 *       #[Env('APP_NAME', default: 'Bingo')]
 *       public readonly string $name,
 *   ) {}
 *
 * The ConfigLoader reads this attribute, fetches $_ENV[$key], casts the raw
 * string to the declared PHP type, and passes the value to the constructor.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Env
{
    public function __construct(
        public string $key,
        public mixed $default = null,
    ) {
    }
}
