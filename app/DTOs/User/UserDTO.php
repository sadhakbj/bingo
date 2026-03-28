<?php

declare(strict_types=1);

namespace App\DTOs\User;

use Core\Data\DataTransferObject;

class UserDTO extends DataTransferObject
{
    public readonly int $id;
    public readonly string $email;
    public readonly string $name;
    public readonly ?int $age;
    public readonly ?string $bio;
    public readonly string $created_at;
    public readonly string $updated_at;
    public readonly array $posts;

    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function isAdult(): bool
    {
        return $this->age && $this->age >= 18;
    }
    
    public function getMetadata(): array
    {
        return [
            'is_adult' => $this->isAdult(),
            'post_count' => count($this->posts),
            'profile_complete' => $this->isProfileComplete()
        ];
    }
    
    private function isProfileComplete(): bool
    {
        return !empty($this->email) && 
               !empty($this->name) && 
               !is_null($this->age) && 
               !empty($this->bio);
    }
}