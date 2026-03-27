<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "🔧 Testing Dotenv Configuration\n\n";

// Load dotenv
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
    echo "✅ .env file loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Failed to load .env file: " . $e->getMessage() . "\n";
    exit(1);
}

// Test environment variables
echo "\n=== Environment Variables ===\n";
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'not set') . "\n";
echo "APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'not set') . "\n";
echo "DB_CONNECTION: " . ($_ENV['DB_CONNECTION'] ?? 'not set') . "\n";
echo "CORS_ALLOWED_ORIGINS: " . ($_ENV['CORS_ALLOWED_ORIGINS'] ?? 'not set') . "\n";

// Test application bootstrap with dotenv
echo "\n=== Testing Application Bootstrap ===\n";
try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "✅ Application bootstrapped successfully\n";
    echo "Environment: " . $app->getConfig()['environment'] . "\n";
} catch (Exception $e) {
    echo "❌ Application bootstrap failed: " . $e->getMessage() . "\n";
}

echo "\n✅ Dotenv integration complete! 🚀\n";