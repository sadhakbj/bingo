<?php

declare(strict_types=1);

namespace Tests\Stubs\DTOs;

use Core\Data\DataTransferObject;

/**
 * Minimal concrete DTO for unit testing DataTransferObject internals.
 * No validation constraints — tests only fill/toArray/helpers.
 */
class SimpleDTOStub extends DataTransferObject
{
    public string $name;
    public ?int $age = null;
    public ?string $bio = null;
    public ?array $tags = null;
}
