<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\DTOs\CreateUserDTO;
use App\Services\UserService;

echo "=== NestJS-Style DTO Flow Test ===\n\n";

try {
    // Simulate request data (like @Body() in NestJS)
    $requestData = [
        'email' => 'john@example.com',
        'name' => 'John Doe',
        'age' => 25,
        'bio' => 'Software developer'
    ];
    
    // Step 1: Framework validates and creates DTO (like ValidationPipe in NestJS)
    echo "1. Creating and validating DTO from request...\n";
    $dto = CreateUserDTO::from($requestData);
    echo "✅ DTO created and validated successfully\n\n";
    
    // Step 2: Pass DTO to controller, then to service (like NestJS)
    echo "2. Passing DTO to service (like NestJS flow)...\n";
    $userService = new UserService();
    $response = $userService->createUser($dto);
    echo "✅ Service processed DTO successfully\n\n";
    
    echo "3. Final response:\n";
    echo $response->toJson() . "\n\n";
    
    // Test validation failure
    echo "=== Testing Validation (Invalid Data) ===\n";
    $invalidData = [
        'email' => 'invalid-email', 
        'name' => '',  // Too short
        'age' => 150   // Too old
    ];
    
    $invalidDto = CreateUserDTO::from($invalidData);
    
} catch (Exception $e) {
    echo "✅ Validation caught error (expected): " . $e->getMessage() . "\n\n";
}

echo "=== Architecture Comparison ===\n";
echo "❌ Old way: Request → Extract Data → Service\n";
echo "✅ NestJS way: Request → Validated DTO → Service\n";
echo "✅ DTO flows through entire system with type safety!\n";