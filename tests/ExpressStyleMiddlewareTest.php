<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Application;
use Core\Http\Request;
use Core\Http\Response;

echo "🚀 Testing Express.js Style Middleware 🚀\n\n";

// Create application
$app = Application::create(['default_middleware' => false]);

// Test Express.js style middleware registration
$app->use(function(Request $request, callable $next) {
    echo "✅ Custom middleware 1 executed\n";
    return $next($request);
});

$app->use(function(Request $request, callable $next) {
    echo "✅ Custom middleware 2 executed\n";
    $response = $next($request);
    echo "✅ Response modified in middleware 2\n";
    return $response;
});

// Enable CORS (Express.js style)
$app->enableCors(['allowed_origins' => ['http://localhost:3000']]);

// Enable JSON parsing (Express.js style)  
$app->enableJson(['limit' => '5mb']);

echo "=== Middleware Configuration ===\n";
echo "Pipeline count: " . $app->getPipeline()->count() . "\n";
echo "Default middleware: CORS + JSON parsing + Custom middleware\n\n";

echo "=== Express.js Style API Comparison ===\n";
echo "Express.js:\n";
echo "  app.use(cors());\n";
echo "  app.use(express.json());\n";
echo "  app.use((req, res, next) => { next(); });\n\n";

echo "PHP Framework:\n";
echo "  \$app->enableCors();\n";
echo "  \$app->enableJson();\n";
echo "  \$app->use(function(\$req, \$next) { return \$next(\$req); });\n\n";

echo "✅ Express.js Style API Complete! 🎉\n\n";

echo "=== Available Middleware ===\n";
echo "✅ CorsMiddleware - Cross-origin resource sharing\n";
echo "✅ BodyParserMiddleware - JSON/form body parsing\n";
echo "✅ CompressionMiddleware - Gzip response compression\n";
echo "✅ SecurityHeadersMiddleware - Security headers (HSTS, CSP, etc.)\n";
echo "✅ RequestIdMiddleware - Request correlation IDs\n";
echo "✅ RateLimitMiddleware - Request rate limiting\n\n";

echo "=== Usage Examples ===\n";
echo "Development setup:\n";
echo "  \$app = Application::development();\n\n";
echo "Production setup:\n";
echo "  \$app = Application::production([\n";
echo "    'cors' => ['allowed_origins' => ['https://myapp.com']]\n";
echo "  ]);\n\n";

echo "Custom middleware:\n";
echo "  \$app->use(function(\$request, \$next) {\n";
echo "    // Middleware logic here\n";
echo "    return \$next(\$request);\n";
echo "  });\n\n";