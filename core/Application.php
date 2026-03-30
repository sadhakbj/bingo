<?php

declare(strict_types=1);

namespace Core;

use Config\AppConfig;
use Config\DbConfig;
use Core\Config\ConfigLoader;
use Core\Config\DatabaseConfig;
use Core\Container\Container;
use Core\Contracts\ExceptionHandlerInterface;
use Core\Database\Database;
use Core\Exceptions\ExceptionHandler;
use Core\Http\Middleware\MiddlewarePipeline;
use Core\Http\Request;
use Core\Http\Response;
use Core\Router\Router;
use Dotenv\Dotenv;

class Application
{
    private Container $container;
    private Router $router;
    private MiddlewarePipeline $pipeline;
    private array $controllers = [];
    private array $config = [];
    private string $basePath;

    private AppConfig $appConfig;
    private DatabaseConfig $dbConfig;

    private ?ExceptionHandlerInterface $customExceptionHandler = null;

    public function __construct(array $config = [])
    {
        // Determine base path (where composer.json is located)
        $this->basePath = $this->findBasePath();

        // Load environment variables automatically
        $this->loadEnvironmentVariables();

        $this->config = array_merge([
            'default_middleware' => true,
        ], $config);

        // Build typed config objects — #[Env] attributes drive the wiring
        $this->appConfig = ConfigLoader::load(AppConfig::class);
        $this->dbConfig  = $this->bootDatabase();

        $this->container = new Container();
        $this->router    = new Router($this->container);
        $this->pipeline  = MiddlewarePipeline::create($this->container);

        // Register config instances so they are injectable everywhere
        $this->container->instance(AppConfig::class, $this->appConfig);
        $this->container->instance(DatabaseConfig::class, $this->dbConfig);

        // Boot Eloquent with typed database config
        Database::setup($this->dbConfig);

        if ($this->config['default_middleware']) {
            $this->setDefaultMiddleware();
        }
    }

    private function bootDatabase(): DatabaseConfig
    {
        /** @var DbConfig $dbConfig */
        $dbConfig    = ConfigLoader::load(DbConfig::class);
        $defaultName = $dbConfig->default;

        $connections = [];
        foreach ($dbConfig->connections as $name => $driverClass) {
            $connections[$name] = ConfigLoader::load($driverClass);
        }

        if (!isset($connections[$defaultName])) {
            throw new \InvalidArgumentException(
                "DB_CONNECTION is set to '{$defaultName}' but it is not listed in DbConfig::\$connections."
            );
        }

        return new DatabaseConfig($defaultName, $connections);
    }

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
        try {
            return $this->pipeline->process($request, function (Request $req) {
                $response = $this->router->dispatch($req);

                if (!$response instanceof Response) {
                    $response = new Response($response);
                }

                return $response;
            });
        } catch (\Throwable $e) {
            return $this->resolveExceptionHandler()->handle($e);
        }
    }

    /**
     * Override the default exception → JSON mapping (highest priority).
     * Also accepts container binding: singleton(ExceptionHandlerInterface::class, ...).
     */
    public function exceptionHandler(ExceptionHandlerInterface $handler): self
    {
        $this->customExceptionHandler = $handler;

        return $this;
    }

    private function resolveExceptionHandler(): ExceptionHandlerInterface
    {
        if ($this->customExceptionHandler !== null) {
            return $this->customExceptionHandler;
        }

        if ($this->container->has(ExceptionHandlerInterface::class)) {
            return $this->container->make(ExceptionHandlerInterface::class);
        }

        return new ExceptionHandler($this->isDebug());
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        $this->container->compile();
        $request  = Request::createFromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    /**
     * Set default middleware for API applications
     */
    private function setDefaultMiddleware(): void
    {
        if ($this->appConfig->env === 'production') {
            $this->pipeline = MiddlewarePipeline::productionApi([
                'allowed_origins' => $this->config['cors']['allowed_origins'] ?? []
            ]);
        } else {
            $this->pipeline = MiddlewarePipeline::defaultApi();
        }

        // Static factories use `new self()` internally — re-inject the container
        $this->pipeline->setContainer($this->container);
    }

    /**
     * Get the container instance
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Register a singleton — one shared instance for the entire lifecycle.
     * $app->singleton(UserService::class);
     * $app->singleton(CacheInterface::class, RedisCache::class);
     */
    public function singleton(string $abstract, ?string $concrete = null): self
    {
        $this->container->singleton($abstract, $concrete);
        return $this;
    }

    /**
     * Register a transient binding — new instance per resolution.
     * $app->bind(MailerInterface::class, SmtpMailer::class);
     */
    public function bind(string $abstract, ?string $concrete = null): self
    {
        $this->container->bind($abstract, $concrete);
        return $this;
    }

    /**
     * Register a pre-built object instance.
     * $app->instance(Config::class, new Config([...]));
     */
    public function instance(string $abstract, object $instance): self
    {
        $this->container->instance($abstract, $instance);
        return $this;
    }

    /**
     * Resolve a class from the container.
     */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
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
        return $this->appConfig->env;
    }

    /**
     * Check if application is in debug mode
     */
    public function isDebug(): bool
    {
        return $this->appConfig->debug;
    }

    /**
     * Get the typed application config.
     */
    public function getAppConfig(): AppConfig
    {
        return $this->appConfig;
    }

}
