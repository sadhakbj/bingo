<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateUserDTO;
use App\DTOs\User\UserDTO;
use App\Models\User;
use Bingo\Exceptions\Http\ConflictException;
use Bingo\Exceptions\Http\NotFoundException;

class UserService
{
    public function createUser(CreateUserDTO $dto): UserDTO
    {
        if (User::where('email', $dto->email)->exists()) {
            throw new ConflictException('Email already exists');
        }

        $user = User::create([
            'name'  => $dto->name,
            'email' => $dto->email,
            'age'   => $dto->age ?? null,
            'bio'   => $dto->bio ?? null,
        ]);

        return UserDTO::fromModel($user->loadMissing('posts'));
    }

    public function getUserById(int $id): UserDTO
    {
        $user = User::with('posts')->find($id);

        if (!$user) {
            throw new NotFoundException('User not found');
        }

        return UserDTO::fromModel($user);
    }
}
