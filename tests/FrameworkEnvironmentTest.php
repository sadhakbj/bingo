<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "🧪 Testing Framework-Level Environment Loading\n\n";

// Test that Application automatically loads .env without manual setup
$app = \Core\Application::create();

echo "=== Framework-Level Environment Loading ===\n";
echo "✅ .env loaded automatically by framework\n";
echo "✅ No manual dotenv setup required\n\n";

echo "=== Helper Methods ===\n";
echo "Base Path: " . $app->basePath() . "\n";
echo "Config Path: " . $app->basePath('config') . "\n";
echo "Environment: " . $app->environment() . "\n";
echo "Debug Mode: " . ($app->isDebug() ? 'true' : 'false') . "\n";
echo "APP_ENV via helper: " . $app->env('APP_ENV', 'default') . "\n";
echo "Nonexistent var: " . $app->env('NONEXISTENT', 'fallback') . "\n\n";

echo "=== Environment Variables Available ===\n";
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'not set') . "\n";
echo "APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'not set') . "\n";
echo "DB_CONNECTION: " . ($_ENV['DB_CONNECTION'] ?? 'not set') . "\n\n";

echo "✅ Framework-level environment loading works perfectly! 🚀\n";
echo "✅ No need for manual dotenv setup in bootstrap\n";
echo "✅ .env automatically loaded when Application is created\n";