<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateUserDTO;
use App\DTOs\User\UserDTO;
use App\Models\User;
use Core\DTOs\Http\ApiResponse;
use Exception;

class UserService
{
    public function createUser(CreateUserDTO $dto): ApiResponse
    {
        try {
            $this->validateBusinessRules($dto);

            $user = $this->persistUser($dto);

            $userDTO = UserDTO::fromModel($user->load('posts'));

            return ApiResponse::success(
                data: $userDTO,
                message: 'User created successfully',
                statusCode: 201,
                meta: [
                    'user_metadata' => $userDTO->getMetadata()
                ]
            );

        } catch (Exception $e) {
            return ApiResponse::error(
                message: 'Failed to create user: ' . $e->getMessage(),
                statusCode: 500
            );
        }
    }

    private function validateBusinessRules(CreateUserDTO $dto): void
    {
        if ($this->emailExists($dto->email)) {
            throw new Exception('Email already exists');
        }
    }

    private function emailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    private function persistUser(CreateUserDTO $dto): User
    {
        return User::create([
            'name'  => $dto->name,
            'email' => $dto->email,
            'age'   => $dto->age ?? null,
            'bio'   => $dto->bio ?? null,
        ]);
    }

    public function getUserById(int $id): ApiResponse
    {
        $user = User::with('posts')->find($id);

        if (!$user) {
            return ApiResponse::notFound('User not found');
        }

        $userDTO = UserDTO::fromModel($user);

        return ApiResponse::success(
            data: $userDTO,
            meta: [
                'user_metadata' => $userDTO->getMetadata()
            ]
        );
    }
}
