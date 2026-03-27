
# Attribute Framework

A high-performance PHP 8.5+ framework designed for **API-first development** and **microservices**. Features clean attribute-based routing, powerful middleware system, and zero-configuration setup using the best Symfony packages.

<div align="center">

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-8892BF.svg)]()
[![API First](https://img.shields.io/badge/design-API%20First-blue.svg)]()
[![Microservices](https://img.shields.io/badge/optimized%20for-Microservices-green.svg)]()
[![Modern PHP](https://img.shields.io/badge/modern-PHP%208.5+-purple.svg)]()
[![ORM](https://img.shields.io/badge/ORM-Eloquent-ff2d20.svg)]()

</div>

## 🚀 Why This Framework?

**Built for developers who want:**
- 🏷️ **Clean Attribute-Based Routing** - No configuration files needed
- 🔗 **Powerful Middleware System** with intuitive `app.use()` API  
- 🏗️ **Clean Architecture** with organized separation of concerns
- ⚡ **Zero Configuration** - works out of the box
- 🌐 **API-First Design** - optimized for microservices
- 🎯 **Modern PHP 8.5+** with strict typing and performance

```php
// Clean attribute-based routing
#[ApiController('/users')]
class UsersController {
  #[Get('/{id}')]
  public function findOne(#[Param('id')] int $id, #[Query('include')] ?string $include = null): Response {
    return Response::json($this->usersService->findOne($id, $include));
  }
  
  #[Post('/')]
  public function create(CreateUserRequest $request): Response {
    $user = $this->userService->create($request->toDTO());
    return Response::json($user, 201);
  }
}
```

---

## 🏁 Quick Start

### Prerequisites
- PHP 8.3+
- Composer

### Installation

```bash
git clone <repository>
cd php-attribute-framework
composer install
cp .env.example .env  # Configure your environment
```

### Bootstrap Your Application

Create your `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

APP_ENV=local
APP_DEBUG=true
```

The framework uses **Laravel-style bootstrap architecture**:

**`public/index.php`** (Entry Point):
```php
<?php

require_once __DIR__ . '/../bootstrap/app.php';
```

**`bootstrap/app.php`** (Configuration):
```php
<?php

use Core\Application;
use Core\Database\Database;

// Create application with Express.js-style API
$app = Application::create();

// Setup database (uses .env automatically)
Database::setup();

// Express.js-style middleware
$app->use(\Core\Http\Middleware\CorsMiddleware::class)
    ->use(\Core\Http\Middleware\BodyParserMiddleware::class)
    ->use(\Core\Http\Middleware\SecurityHeadersMiddleware::class);

// Manual controller registration (no auto-discovery overhead)
$app->controllers([
    \App\Http\Controllers\HomeController::class,
    \App\Http\Controllers\UsersController::class,
    // Add your controllers here
]);

return $app;
```

### Run Development Server

```bash
php -S localhost:8000 -t public
```

### Your First Controller

Create `app/Http/Controllers/PostsController.php`:

```php
<?php

namespace App\Http\Controllers;

use Core\Attributes\ApiController;
use Core\Attributes\Get;
use Core\Attributes\Post;
use Core\Attributes\Body;
use Core\Attributes\Query;
use Core\Http\Response;
use App\DTOs\CreatePostDTO;

#[ApiController('/posts')]
class PostsController
{
    #[Get('')]
    public function index(
        #[Query('page')] int $page = 1,
        #[Query('limit')] int $limit = 10
    ): Response {
        return Response::json([
            'posts' => [],
            'pagination' => compact('page', 'limit')
        ]);
    }

    #[Post('')]
    public function create(#[Body] CreatePostDTO $dto): Response {
        // DTO automatically validated and typed
        return Response::json([
            'message' => 'Post created',
            'data' => $dto->toArray()
        ], 201);
    }
}
```

Register in `bootstrap/app.php`:
```php
$app->controllers([
    \App\Http\Controllers\PostsController::class,
]);
```

**That's it!** Your API is ready at:
- `GET http://localhost:8000/posts?page=1&limit=5`
- `POST http://localhost:8000/posts` (with JSON body)

---

## 🎯 Core Features

### 🌐 Powerful Middleware System
Intuitive HTTP middleware pipeline with chainable APIs:

```php
// In bootstrap/app.php
$app->use(\Core\Http\Middleware\CorsMiddleware::class, [
        'allow_origins' => ['http://localhost:3000', 'https://yourdomain.com'],
        'allow_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allow_headers' => ['Content-Type', 'Authorization'],
        'allow_credentials' => true,
    ])
    ->use(\Core\Http\Middleware\BodyParserMiddleware::class)
    ->use(\Core\Http\Middleware\CompressionMiddleware::class)
    ->use(\Core\Http\Middleware\SecurityHeadersMiddleware::class)
    ->use(\Core\Http\Middleware\RequestIdMiddleware::class)
    ->use(\Core\Http\Middleware\RateLimitMiddleware::class, [
        'requests_per_minute' => 60,
        'burst_limit' => 10
    ]);
```

**Built-in Middleware:**

| Middleware | Purpose | Configuration |
|------------|---------|---------------|
| `CorsMiddleware` | CORS headers + preflight | origins, methods, headers, credentials |
| `BodyParserMiddleware` | JSON/form parsing | max_size, allowed_types |
| `CompressionMiddleware` | Gzip/deflate responses | level, min_length |
| `SecurityHeadersMiddleware` | OWASP security headers | CSP, HSTS, frame options |
| `RequestIdMiddleware` | Unique request tracking | header_name, uuid_version |
| `RateLimitMiddleware` | Request limiting | requests_per_minute, burst_limit |

### 🔧 Laravel-Style Architecture
Clean separation of concerns with familiar Laravel patterns:

```
├── app/
│   └── Http/                  # Laravel-style HTTP layer
│       ├── Controllers/       # Route handlers
│       ├── Middleware/        # Custom middleware
│       └── Requests/          # Form requests
│   ├── DTOs/                  # Data Transfer Objects
│   ├── Models/                # Eloquent models
│   └── Services/              # Business logic
├── bootstrap/
│   └── app.php               # Application configuration
├── core/                     # Framework internals
├── public/
│   └── index.php            # Simple entry point
└── .env                     # Environment configuration
```

### 🌍 Automatic Environment Loading
Framework-level `.env` support (like Laravel):

```php
// .env file automatically loaded in Application constructor
// No manual dotenv setup required

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
APP_DEBUG=true
CACHE_DRIVER=file

// Access anywhere with $_ENV or getenv()
$debug = $_ENV['APP_DEBUG'] ?? false;
```

### 🏷️ NestJS-Style Attribute Routing
Define routes directly on methods using PHP 8 attributes:

```php
#[ApiController('/api/v1/users')]
class UsersController {
    #[Get('')]                     // GET /api/v1/users
    #[Get('/{id}')]               // GET /api/v1/users/123  
    #[Post('')]                   // POST /api/v1/users
    #[Put('/{id}')]              // PUT /api/v1/users/123
    #[Delete('/{id}')]           // DELETE /api/v1/users/123
    #[Patch('/{id}/status')]     // PATCH /api/v1/users/123/status
}
```

### 🎪 Smart Parameter Binding
Extract request data with clean attribute decorators:

```php
public function createUser(
    #[Body] CreateUserDTO $userData,           // Request body → DTO
    #[Query('notify')] bool $notify = true,    // Query params with type casting
    #[Headers('x-api-version')] string $version, // Headers
    #[Param('id')] int $userId,               // Route parameters  
    #[UploadedFile('avatar')] $file = null    // File uploads
): Response {
    // All parameters automatically extracted, validated, and typed
}
```

**Available Parameter Attributes:**
| Attribute | Purpose | Example |
|-----------|---------|----------|
| `#[Body]` | Request body → DTO | `#[Body] CreateUserDTO $dto` |
| `#[Query('key')]` | Query parameters | `#[Query('limit')] int $limit = 10` |
| `#[Param('key')]` | Route parameters | `#[Param('id')] int $id` |
| `#[Headers('key')]` | HTTP headers | `#[Headers('authorization')] string $auth` |
| `#[Request]` | Full request object | `#[Request] Request $request` |
| `#[UploadedFile('key')]` | Single file upload | `#[UploadedFile('avatar')] $file` |
| `#[UploadedFiles]` | All uploaded files | `#[UploadedFiles] array $files` |

### 📋 DTOs & Validation
Type-safe data transfer objects with automatic validation:

```php
namespace App\DTOs;

use Core\Data\DataTransferObject;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO extends DataTransferObject
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public readonly string $email;
    
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public readonly string $name;
    
    #[Assert\Positive]
    #[Assert\Range(min: 13, max: 120)]
    public readonly ?int $age;
}
```

Usage in controllers:
```php
#[Post('/users')]
public function create(#[Body] CreateUserDTO $dto): Response {
    // $dto is automatically:
    // 1. Populated from request JSON
    // 2. Validated using Symfony Validator
    // 3. Type-cast to correct types
    // 4. Throws 422 with errors if validation fails
    
    return Response::json($dto->toArray(), 201);
}
```

### 🛡️ Advanced Middleware System
Apply middleware globally or per-controller/route:

**Global Middleware:**
```php
// In bootstrap/app.php
$app->use(\Core\Http\Middleware\CorsMiddleware::class)
    ->use(\Core\Http\Middleware\BodyParserMiddleware::class)
    ->use(\App\Http\Middleware\AuthMiddleware::class);

// Applied to ALL requests
```

**Controller/Route Middleware:**
```php
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\LogMiddleware;

#[ApiController('/admin')]
#[Middleware([AuthMiddleware::class, LogMiddleware::class])]
class AdminController {
    // All methods protected by auth + logging
}

// Or per-method:
class UsersController {
    #[Get('/public')]
    public function publicRoute() {} // No middleware
    
    #[Get('/private')]
    #[Middleware([AuthMiddleware::class])]
    public function privateRoute() {} // Auth required
}
```

**Custom Middleware:**
```php
namespace App\Http\Middleware;

use Core\Http\Request;
use Core\Http\Response;

class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->headers->get('Authorization');
        
        if (!$token) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        // Validate token...
        
        return $next($request);
    }
}
```

### 🗄️ Eloquent ORM Integration
Full Laravel Eloquent support for elegant database operations:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'age'];
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Usage in controllers
#[Get('/users/{id}/posts')]
public function getUserPosts(#[Param('id')] int $userId): Response {
    $posts = User::findOrFail($userId)->posts()->with('comments')->get();
    return Response::json($posts);
}
```

---

## 📚 Advanced Features

### 🔄 Smart Route Handling
Framework automatically handles trailing slashes Laravel-style:

```php
#[Get('/users')]       // Matches both /users AND /users/
#[Get('/users/{id}')]  // Matches /users/123, /users/123/
```

### 🎯 Type System Integration
Automatic type casting based on PHP type hints:

```php
public function search(
    #[Query('limit')] int $limit,     // "10" → 10
    #[Query('active')] bool $active,  // "true" → true  
    #[Query('price')] float $price    // "19.99" → 19.99
): Response {
    // All types automatically converted
}
```

### 🚨 Error Handling
Consistent JSON error responses for API controllers:

```http
HTTP/1.1 422 Unprocessable Entity
{
  "errors": {
    "email": "This field is required",
    "age": "Must be at least 13"
  }
}

HTTP/1.1 404 Not Found  
{
  "error": "Not Found"
}
```

### 📁 Clean Architecture
Organized, scalable project structure:

```
├── app/
│   └── Http/                   # HTTP layer organization
│       ├── Controllers/        # Route handlers 
│       ├── Middleware/         # Custom request/response middleware
│       └── Requests/           # Form/validation requests
│   ├── DTOs/                   # Data Transfer Objects
│   ├── Models/                 # Eloquent models
│   └── Services/               # Business logic
├── bootstrap/
│   └── app.php                 # Application configuration
├── core/                       # Framework internals
│   ├── Attributes/             # Route & parameter attributes  
│   ├── Http/
│   │   ├── Middleware/         # Built-in middleware
│   │   ├── Request.php         # Request handling
│   │   └── Response.php        # Response handling
│   ├── Router/                 # Route matching & dispatching
│   └── Data/                   # DTO base classes
├── database/
│   └── migrations/             # Database migrations
├── public/
│   └── index.php              # Simple application entry point
└── .env                       # Environment configuration
```

---

## 🧠 Design Philosophy

### **API-First Architecture**
Purpose-built for modern microservices and APIs:

- **Zero overhead** → Manual controller registration, no filesystem scanning
- **Type safety** → End-to-end typing from request to response  
- **Performance** → Minimal abstraction, maximum speed
- **Developer Experience** → Clean, intuitive APIs and patterns

### **Modern PHP Standards**
- PHP 8.5+ features (attributes, readonly properties, union types, strict typing)
- PSR-compliant autoloading and structure
- Symfony components for reliability and performance
- Best practices from Laravel ecosystem

---

## 🔧 Application API

The framework provides an Express.js-inspired API with Laravel-style architecture:

### Application Setup
```php
use Core\Application;

// Create application (automatically loads .env)
$app = Application::create();

// Express.js-style middleware chain
$app->use(\Core\Http\Middleware\CorsMiddleware::class)
    ->use(\Core\Http\Middleware\BodyParserMiddleware::class)
    ->use(\Core\Http\Middleware\SecurityHeadersMiddleware::class);

// Manual controller registration (performance-focused)
$app->controllers([
    \App\Http\Controllers\HomeController::class,
    \App\Http\Controllers\UsersController::class,
    \App\Http\Controllers\PostsController::class,
]);

// Start handling requests
$app->run();
```

### Environment Configuration
```php
// .env automatically loaded in Application constructor  
// No manual setup required (Laravel-style)

// In .env file:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret

APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.myapp.com

// Access in your code:
$debug = $_ENV['APP_DEBUG'] ?? false;
$dbHost = getenv('DB_HOST');
```

### Middleware Configuration
```php
// Built-in middleware with options
$app->use(\Core\Http\Middleware\CorsMiddleware::class, [
    'allow_origins' => ['https://myapp.com'],
    'allow_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allow_headers' => ['Content-Type', 'Authorization'],
    'allow_credentials' => true,
]);

$app->use(\Core\Http\Middleware\RateLimitMiddleware::class, [
    'requests_per_minute' => 100,
    'burst_limit' => 20
]);

// Custom middleware
$app->use(\App\Http\Middleware\CustomAuthMiddleware::class);
```

---

## 📖 Complete API Reference

### Application Methods
```php
Application::create()                           // Create new application instance
$app->use($middleware, $options = [])          // Add global middleware (Express.js-style)  
$app->controllers($controllers)                // Register controller classes
$app->run()                                   // Start request handling
```

### Built-in Middleware Classes
```php
\Core\Http\Middleware\CorsMiddleware::class           // CORS headers + preflight
\Core\Http\Middleware\BodyParserMiddleware::class     // JSON/form parsing
\Core\Http\Middleware\CompressionMiddleware::class    // Gzip/deflate compression
\Core\Http\Middleware\SecurityHeadersMiddleware::class // OWASP security headers
\Core\Http\Middleware\RequestIdMiddleware::class      // Request ID tracking
\Core\Http\Middleware\RateLimitMiddleware::class      // Request rate limiting
```

### Middleware Options
```php
// CorsMiddleware options
'allow_origins' => ['*'] | ['https://domain.com'],
'allow_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allow_headers' => ['Content-Type', 'Authorization'],
'allow_credentials' => true | false,
'max_age' => 86400

// BodyParserMiddleware options  
'max_size' => 1024 * 1024,  // 1MB default
'allowed_types' => ['application/json', 'application/x-www-form-urlencoded']

// CompressionMiddleware options
'level' => 6,         // Compression level 1-9
'min_length' => 1024, // Minimum response size to compress

// SecurityHeadersMiddleware options
'content_security_policy' => "default-src 'self'",
'strict_transport_security' => 'max-age=31536000; includeSubDomains',
'x_frame_options' => 'DENY',
'x_content_type_options' => 'nosniff'

// RateLimitMiddleware options
'requests_per_minute' => 60,
'burst_limit' => 10,
'identifier' => 'ip'  // 'ip' or callable```

### Environment Configuration
Framework automatically loads `.env` file (Laravel-style):

```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret

# Application Configuration  
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=https://api.myapp.com

# Custom Configuration
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

**Access in code:**
```php
// Using $_ENV (recommended)
$debug = $_ENV['APP_DEBUG'] ?? false;
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';

// Using getenv()
$dbName = getenv('DB_DATABASE');```

### Route Attributes
```php
#[Get('/path')]           // GET request
#[Post('/path')]          // POST request  
#[Put('/path')]           // PUT request
#[Patch('/path')]         // PATCH request
#[Delete('/path')]        // DELETE request
#[Options('/path')]       // OPTIONS request
#[Head('/path')]          // HEAD request
#[Route('/path', 'ANY')]  // Custom HTTP method
```

### Controller Attributes
```php
#[ApiController('/prefix')]              // API controller with prefix
#[Middleware([AuthMiddleware::class])]   // Apply middleware
```

### Parameter Attributes
```php
#[Body]                              // Parse request body to DTO
#[Query('key')]                      // Extract query parameter
#[Param('key')]                      // Extract route parameter  
#[Headers('key')]                    // Extract header value
#[Request]                           // Inject full request object
#[UploadedFile('key')]              // Single file upload
#[UploadedFiles]                     // All uploaded files
```

### Response Methods
```php
Response::json($data, $status = 200)     // JSON response
Response::html($content, $status = 200)  // HTML response  
Response::redirect($url, $status = 302)  // Redirect response
```

---

## 🎯 Framework Comparison

| Feature | Laravel | Express.js | NestJS | This Framework |
|---------|---------|------------|--------|----------------|
| Route Attributes | ❌ | ❌ | ✅ | ✅ |
| Parameter Binding | ❌ | ❌ | ✅ | ✅ |
| Middleware Pipeline | ✅ | ✅ | ✅ | ✅ |
| Express.js-style API | ❌ | ✅ | ❌ | ✅ |
| DTOs Built-in | ❌ | ❌ | ✅ | ✅ |
| Eloquent ORM | ✅ | ❌ | ❌ | ✅ |
| Type Safety | Partial | ❌ | ✅ | ✅ |
| Environment Loading | ✅ | Manual | Manual | ✅ |
| API-First Design | Partial | ✅ | ✅ | ✅ |

**Modern advantage**: Combines attribute-based routing, powerful middleware, and mature PHP ecosystem.

---

## 🔬 Microservices & API-First Features

### Performance-Optimized Registration
Manual controller registration with zero filesystem scanning:

```php
// No auto-discovery overhead
$app->controllers([
    \App\Http\Controllers\UsersController::class,
    \App\Http\Controllers\PostsController::class,
]);

// Framework does NOT scan directories on each request
// Perfect for high-performance microservices
```

### Production-Ready Middleware
Complete HTTP layer for microservices:

```php
// CORS for cross-origin requests
$app->use(\Core\Http\Middleware\CorsMiddleware::class, [
    'allow_origins' => ['https://frontend.myapp.com'],
    'allow_credentials' => true
]);

// Rate limiting for API protection  
$app->use(\Core\Http\Middleware\RateLimitMiddleware::class, [
    'requests_per_minute' => 100
]);

// Security headers (OWASP compliant)
$app->use(\Core\Http\Middleware\SecurityHeadersMiddleware::class);

// Request compression for bandwidth efficiency  
$app->use(\Core\Http\Middleware\CompressionMiddleware::class);
```

### Health Check Example
```php
#[ApiController('/health')]
class HealthController {
    #[Get('')]
    public function check(): Response {
        return Response::json([
            'status' => 'healthy',
            'timestamp' => time(),
            'service' => 'user-api',
            'version' => '1.0.0'
        ]);
    }
}
```

### Docker-Ready Structure
```dockerfile
FROM php:8.3-fpm
COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader
EXPOSE 9000
CMD ["php-fpm"]
```

---

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup
```bash
git clone <repository>
cd php-attribute-framework
composer install
php -S localhost:8000 -t public
```

### Running Tests
```bash
composer test
```

---

## 📜 License

MIT License. See [LICENSE](LICENSE) for details.

---

<div align="center">

**Made with ❤️ for developers who love clean architecture**

[Documentation](docs/) • [Examples](examples/) • [Contributing](CONTRIBUTING.md)

</div>