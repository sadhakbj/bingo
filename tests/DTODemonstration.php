<?php

namespace Tests;

use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UserDTO;
use App\Services\UserService;
use Core\Data\DTOCollection;
use Core\DTOs\Http\ApiResponse;

/**
 * Demonstration of DTO usage in large-scale framework
 * This shows how DTOs provide type safety, testability, and clear contracts
 */
class DTODemonstration
{
    public function demonstrateBasicDTOUsage(): void
    {
        echo "=== Basic DTO Usage Demo ===\n";
        
        // Create DTO from array (typical API input)
        $createUserDTO = CreateUserDTO::from([
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'age' => 25,
            'bio' => 'Software developer with 5 years experience',
            'metadata' => ['source' => 'api', 'version' => '1.0']
        ]);
        
        echo "Created DTO: " . $createUserDTO->toJson() . "\n\n";
        
        // Use service layer
        $userService = new UserService();
        $response = $userService->createUser($createUserDTO);
        
        echo "Service Response: " . $response->toJson() . "\n\n";
    }
    
    public function demonstrateCollections(): void
    {
        echo "=== DTO Collections Demo ===\n";
        
        $usersData = [
            ['id' => 1, 'email' => 'user1@test.com', 'name' => 'User One', 'age' => 25, 'bio' => 'Bio 1', 'created_at' => '2024-01-01', 'updated_at' => '2024-01-01', 'posts' => []],
            ['id' => 2, 'email' => 'user2@test.com', 'name' => 'User Two', 'age' => 35, 'bio' => 'Bio 2', 'created_at' => '2024-01-02', 'updated_at' => '2024-01-02', 'posts' => []],
            ['id' => 3, 'email' => 'user3@test.com', 'name' => 'User Three', 'age' => 17, 'bio' => 'Bio 3', 'created_at' => '2024-01-03', 'updated_at' => '2024-01-03', 'posts' => []],
        ];
        
        $userCollection = DTOCollection::make($usersData, UserDTO::class);
        
        echo "Total users: " . $userCollection->count() . "\n";
        
        // Filter adult users
        $adultUsers = $userCollection->filter(function(UserDTO $user) {
            return $user->isAdult();
        });
        
        echo "Adult users: " . $adultUsers->count() . "\n";
        
        // Map to display names
        $names = $userCollection->map(function(UserDTO $user) {
            return $user->getDisplayName();
        });
        
        echo "User names: " . implode(', ', $names) . "\n\n";
    }
    
    public function demonstrateApiResponses(): void
    {
        echo "=== API Response DTOs Demo ===\n";
        
        // Success response
        $successResponse = ApiResponse::success(
            data: ['user_id' => 123],
            message: 'User created successfully',
            statusCode: 201
        );
        
        echo "Success: " . $successResponse->toJson() . "\n";
        
        // Error response
        $errorResponse = ApiResponse::validation([
            'email' => 'Email is required',
            'age' => 'Age must be at least 18'
        ]);
        
        echo "Validation Error: " . $errorResponse->toJson() . "\n";
        
        // Not found
        $notFoundResponse = ApiResponse::notFound('User not found');
        echo "Not Found: " . $notFoundResponse->toJson() . "\n\n";
    }
    
    public function demonstrateTypeSeafetyBenefits(): void
    {
        echo "=== Type Safety Benefits ===\n";
        
        $userDTO = UserDTO::from([
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'age' => 25,
            'bio' => 'Test bio',
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-01',
            'posts' => []
        ]);
        
        // IDE autocomplete and type checking works perfectly
        echo "User ID: " . $userDTO->id . "\n";
        echo "Is Adult: " . ($userDTO->isAdult() ? 'Yes' : 'No') . "\n";
        echo "Display Name: " . $userDTO->getDisplayName() . "\n";
        echo "Metadata: " . json_encode($userDTO->getMetadata()) . "\n\n";
    }
    
    public function runAllDemos(): void
    {
        $this->demonstrateBasicDTOUsage();
        $this->demonstrateCollections();
        $this->demonstrateApiResponses();
        $this->demonstrateTypeSeafetyBenefits();
        
        echo "=== Framework Benefits Summary ===\n";
        echo "✅ Type Safety: Full IDE support and compile-time checks\n";
        echo "✅ Testability: Easy to mock and unit test\n";
        echo "✅ Consistency: Standardized data structures across layers\n";
        echo "✅ Performance: Optimized object creation and memory usage\n";
        echo "✅ Maintainability: Clear contracts and separation of concerns\n";
        echo "✅ Documentation: Self-documenting code structure\n";
        echo "✅ Extensibility: Easy to add new DTOs and modify existing ones\n";
    }
}

// Run the demonstration
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $demo = new DTODemonstration();
    $demo->runAllDemos();
}