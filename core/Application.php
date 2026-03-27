<?php

namespace Core;

use Core\Http\Middleware\MiddlewarePipeline;
use Core\Http\Request;
use Core\Http\Response;
use Core\Router\Router;
use Dotenv\Dotenv;

class Application
{
    private Router $router;
    private MiddlewarePipeline $pipeline;
    private array $controllers = [];
    private array $config = [];
    private string $basePath;

    public function __construct(array $config = [])
    {
        // Determine base path (where composer.json is located)
        $this->basePath = $this->findBasePath();
        
        // Load environment variables automatically
        $this->loadEnvironmentVariables();
        
        $this->config = array_merge([
            'environment' => $_ENV['APP_ENV'] ?? 'development',
            'default_middleware' => true,
        ], $config);

        $this->router = new Router();
        $this->pipeline = MiddlewarePipeline::create();
        
        if ($this->config['default_middleware']) {
            $this->setDefaultMiddleware();
        }
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnvironmentVariables(): void
    {
        try {
            $dotenv = Dotenv::createImmutable($this->basePath);
            $dotenv->safeLoad();
        } catch (\Exception $e) {
            // .env file is optional, so we don't fail if it's missing
        }
    }

    /**
     * Find the base path of the application
     */
    private function findBasePath(): string
    {
        $currentDir = __DIR__;
        
        // Walk up directories until we find composer.json
        while (!file_exists($currentDir . '/composer.json')) {
            $parentDir = dirname($currentDir);
            
            // Prevent infinite loop
            if ($parentDir === $currentDir) {
                return dirname(__DIR__); // Fallback to framework parent dir
            }
            
            $currentDir = $parentDir;
        }
        
        return $currentDir;
    }

    /**
     * Intuitive use() method to add middleware
     */
    public function use($middleware): self
    {
        $this->pipeline->use($middleware);
        return $this;
    }

    /**
     * Register a controller
     */
    public function controller(string $controllerClass): self
    {
        $this->controllers[] = $controllerClass;
        $this->router->registerController($controllerClass);
        return $this;
    }

    /**
     * Register multiple controllers
     */
    public function controllers(array $controllers): self
    {
        foreach ($controllers as $controller) {
            $this->controller($controller);
        }
        return $this;
    }

    /**
     * Handle HTTP request
     */
    public function handle(Request $request): Response
    {
        // Process request through middleware pipeline
        return $this->pipeline->process($request, function(Request $req) {
            // Final handler - dispatch through router
            $response = $this->router->dispatch($req);
            
            // Ensure we have a proper Response object
            if (!$response instanceof Response) {
                $response = new Response($response);
            }
            
            return $response;
        });
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        $request = Request::createFromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    /**
     * Set default middleware for API applications
     */
    private function setDefaultMiddleware(): void
    {
        if ($this->config['environment'] === 'production') {
            $this->pipeline = MiddlewarePipeline::productionApi([
                'allowed_origins' => $this->config['cors']['allowed_origins'] ?? []
            ]);
        } else {
            $this->pipeline = MiddlewarePipeline::defaultApi();
        }
    }

    /**
     * Get the router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the middleware pipeline
     */
    public function getPipeline(): MiddlewarePipeline
    {
        return $this->pipeline;
    }

    /**
     * Enable CORS for development
     */
    public function enableCors(array $config = []): self
    {
        $this->use(\Core\Http\Middleware\CorsMiddleware::create($config));
        return $this;
    }

    /**
     * Enable JSON body parsing
     */
    public function enableJson(array $options = []): self
    {
        $this->use(\Core\Http\Middleware\BodyParserMiddleware::json($options));
        return $this;
    }

    /**
     * Application factory method
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }

    /**
     * Create production-ready application
     */
    public static function production(array $config = []): self
    {
        return new self(array_merge([
            'environment' => 'production',
            'default_middleware' => true,
        ], $config));
    }

    /**
     * Create development application
     */
    public static function development(array $config = []): self
    {
        return new self(array_merge([
            'environment' => 'development', 
            'default_middleware' => true,
        ], $config));
    }

    /**
     * Get application configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get registered controllers
     */
    public function getControllers(): array
    {
        return $this->controllers;
    }

    /**
     * Get the base path of the application
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Get environment variable with default
     */
    public function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Get current environment
     */
    public function environment(): string
    {
        return $this->config['environment'];
    }

    /**
     * Check if application is in debug mode
     */
    public function isDebug(): bool
    {
        return $this->env('APP_DEBUG', false) === 'true';
    }
}