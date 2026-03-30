<?php

declare(strict_types=1);

namespace App\DTOs;

use Bingo\Data\DataTransferObject;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO extends DataTransferObject
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    public readonly string $email;

    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'Name must be at least 2 characters', maxMessage: 'Name cannot exceed 50 characters')]
    public readonly string $name;

    #[Assert\Range(notInRangeMessage: 'Age must be between 18 and 120', min: 18, max: 120)]
    public readonly ?int $age;

    #[Assert\Length(max: 500, maxMessage: 'Bio cannot exceed 500 characters')]
    public readonly ?string $bio;

    public readonly ?array $metadata;
}
