<?php

declare(strict_types=1);

namespace Tests\Stubs\DTOs;

use Bingo\Data\DataTransferObject;
use Symfony\Component\Validator\Constraints as Assert;

class InvalidBodyDTOStub extends DataTransferObject
{
    #[Assert\NotBlank]
    public string $name;
}
