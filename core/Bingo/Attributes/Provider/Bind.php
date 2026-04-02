<?php

declare(strict_types=1);

namespace Bingo\Attributes\Provider;

use Attribute;

/**
 * Declares that this interface should be bound to the given concrete class.
 * Place on the interface, not the concrete. Constructor dependencies are auto-wired.
 *
 * Usage:
 *   #[Bind(UserRepository::class)]
 *   interface IUserRepository { ... }
 *
 *   // Transient (new instance per resolution):
 *   #[Bind(UserRepository::class, singleton: false)]
 *   interface IUserRepository { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Bind
{
    public function __construct(
        public string $concrete,
        public bool $singleton = true,
    ) {}
}
