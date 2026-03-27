<?php

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
            // DTO is already validated - just like NestJS!
            // No need to extract data, use DTO directly
            $this->validateBusinessRules($dto);
            
            $user = $this->persistUser($dto);
            
            $userDTO = UserDTO::from((array) $user);
            
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
        // Example business logic validation
        if ($this->emailExists($dto->email)) {
            throw new Exception('Email already exists');
        }

        if ($dto->age && $dto->age < 13) {
            throw new Exception('Users must be at least 13 years old');
        }

        // More business rules...
    }

    private function emailExists(string $email): bool
    {
        // Check if email exists in database
        // This would use your database layer
        return false; // Placeholder
    }

    private function persistUser(CreateUserDTO $dto): object
    {
        // Convert DTO to model for persistence
        // In a real implementation, this would use your ORM/database layer
        
        // For demo, simulate saved user with ID
        $userData = $dto->toArray();
        $userData['id'] = rand(1, 1000);
        $userData['created_at'] = date('Y-m-d H:i:s');
        $userData['updated_at'] = date('Y-m-d H:i:s');
        $userData['posts'] = [];
        
        return (object) $userData; // Simulate model
    }

    public function getUserById(int $id): ApiResponse
    {
        try {
            // Simulate database fetch
            $userData = [
                'id' => $id,
                'email' => 'user@example.com',
                'name' => 'John Doe',
                'age' => 25,
                'bio' => 'Software developer',
                'created_at' => '2024-01-01 12:00:00',
                'updated_at' => '2024-01-01 12:00:00',
                'posts' => []
            ];

            $userDTO = UserDTO::from($userData);
            
            return ApiResponse::success(
                data: $userDTO,
                meta: [
                    'user_metadata' => $userDTO->getMetadata()
                ]
            );
            
        } catch (Exception $e) {
            return ApiResponse::notFound('User not found');
        }
    }
}