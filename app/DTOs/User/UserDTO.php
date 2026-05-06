<?php

declare(strict_types = 1);

namespace App\DTOs\User;

use App\Models\User;

final readonly class UserDTO
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public ?int $age = null,
        public ?string $bio = null,
        public string $created_at = '',
        public string $updated_at = '',
        public array $posts = [],
    ) {
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id        : $user->id,
            email     : $user->email,
            name      : $user->name,
            age       : $user->age,
            bio       : $user->bio,
            created_at: (string) $user->created_at,
            updated_at: (string) $user->updated_at,
            posts     : $user->relationLoaded('posts') ? $user->posts->toArray() : [],
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'email'      => $this->email,
            'name'       => $this->name,
            'age'        => $this->age,
            'bio'        => $this->bio,
            'posts'      => $this->posts,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function isAdult(): bool
    {
        return $this->age !== null && $this->age >= 18;
    }

    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function getMetadata(): array
    {
        return [
            'is_adult'         => $this->isAdult(),
            'post_count'       => count($this->posts),
            'profile_complete' => $this->isProfileComplete(),
        ];
    }

    private function isProfileComplete(): bool
    {
        return !empty($this->email) && !empty($this->name) && $this->age !== null && !empty($this->bio);
    }
}
