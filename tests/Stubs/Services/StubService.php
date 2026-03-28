<?php

declare(strict_types=1);

namespace Tests\Stubs\Services;

/** Zero-arg constructor — simplest resolvable class. */
class StubService
{
    public int $value = 0;
}

/** Has a typed dependency — auto-resolved via reflection. */
class StubServiceWithDep
{
    public function __construct(
        public readonly StubService $service
    ) {}
}

/** Has a scalar param with no default — cannot be auto-resolved. */
class StubServiceWithPrimitive
{
    public function __construct(
        public readonly string $name
    ) {}
}

/** Depends on StubCircularB — triggers circular dependency detection. */
class StubCircularA
{
    public function __construct(
        public readonly StubCircularB $b
    ) {}
}

/** Depends on StubCircularA — completes the cycle. */
class StubCircularB
{
    public function __construct(
        public readonly StubCircularA $a
    ) {}
}
