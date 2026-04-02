<?php

declare(strict_types=1);

namespace Bingo;

use Bingo\Log\RequestContextProcessor;
use Config\CorsConfig;
use Config\DbConfig;
use Config\LogConfig;
use Bingo\Config\ConfigLoader;
use Bingo\Config\DatabaseConfig;
use Bingo\Container\Container;
use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Contracts\HttpResponse;
use Bingo\Database\Database;
use Bingo\Exceptions\ExceptionHandler;
use Bingo\Http\Middleware\CorsMiddleware;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\Http\Router\Router;
use Bingo\Http\StreamedResponse as BingoStreamedResponse;
use Bingo\RateLimit\Contracts\RateLimiterStore;
use Bingo\RateLimit\Store\FileStore;
use Dotenv\Dotenv;
use Bingo\Log\SlogTextFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

class Application
{
    private Container $container;
    private Router $router;
    private MiddlewarePipeline $pipeline;
    private array $controllers = [];
    private string $basePath;

    private ?ExceptionHandlerInterface $customExceptionHandler = null;
    private array $discoveredCommands = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->loadEnvironmentVariables();


        $dbConfig = $this->bootDatabase();

        $this->container = new Container();
        $this->router    = new Router($this->container);
        $this->pipeline  = MiddlewarePipeline::create($this->container);

        $this->container->instance(DatabaseConfig::class, $dbConfig);
        $this->container->instance(RateLimiterStore::class, new FileStore(base_path('storage/rate-limit')));

        // Boot structured logging (Monolog, PSR-3).
        // Override the logger in bootstrap/app.php:
        //   $app->instance(\Psr\Log\LoggerInterface::class, $yourLogger);
        $this->bootLogging();

        // Boot Eloquent with typed database config
        Database::setup($dbConfig);

        // Auto-discover controllers, commands, middleware
        $this->bootDiscovery();

        $this->setDefaultMiddleware();
    }

    private function bootLogging(): void
    {
        $cfg       = ConfigLoader::load(LogConfig::class);
        $processor = new RequestContextProcessor();
        $logger    = new Logger('bingo');

        $level       = Level::fromName(ucfirst($cfg->level));
        $stderrLevel = Level::fromName(ucfirst($cfg->stderrLevel));

        $stderrHandler = new StreamHandler('php://stderr', $stderrLevel);
        $fileHandler   = new RotatingFileHandler(base_path($cfg->path), 30, $level);

        // Colors only when stderr is an interactive terminal — never in files or pipes
        $isTerminal = defined('STDERR') && stream_isatty(\STDERR);

        $stderrHandler->setFormatter(match ($cfg->format) {
            'json'  => new JsonFormatter(),
            default => new SlogTextFormatter(colors: $isTerminal, timeFormat: $cfg->timeFormat),
        });
        $fileHandler->setFormatter(match ($cfg->format) {
            'json'  => new JsonFormatter(),
            default => new SlogTextFormatter(colors: false, timeFormat: $cfg->timeFormat),
        });

        $logger->pushHandler($fileHandler);
        $logger->pushHandler($stderrHandler);
        $logger->pushProcessor($processor);

        $this->container->instance(LoggerInterface::class, $logger);
        $this->container->instance(RequestContextProcessor::class, $processor);
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

    /**
     * Auto-discover controllers, commands, and other components via attributes.
     *
     * In development, rebuilds cache when files change (filemtime check).
     * In production, requires pre-built cache (fail-fast if missing).
     */
    private function bootDiscovery(): void
    {
        $manager = new \Bingo\Discovery\DiscoveryManager(
            cachePath: base_path('storage/framework/discovery.php'),
            appPath: base_path('app'),
            isProduction: env('APP_ENV', 'development') === 'production',
        );

        $discovered = $manager->load();

        // Register discovered controllers
        if (!empty($discovered['controllers'])) {
            $this->router->registerFromCache($discovered['controllers']);
        }

        // Store discovered commands for console kernel (accessed via getDiscoveredCommands())
        if (!empty($discovered['commands'])) {
            $this->discoveredCommands = $discovered['commands'];
        }
    }

    private function loadEnvironmentVariables(): void
    {
        Dotenv::createImmutable($this->basePath)->load();
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
    public function handle(Request $request): HttpResponse
    {
        try {
            return $this->pipeline->process($request, function (Request $req) {
                $response = $this->router->dispatch($req);

                if (!$response instanceof SymfonyResponse) {
                    $response = new Response((string) $response);
                }

                if (!$response instanceof HttpResponse) {
                    if ($response instanceof SymfonyStreamedResponse) {
                        $response = new BingoStreamedResponse(
                            $response->getCallback(),
                            $response->getStatusCode(),
                            $response->headers->all(),
                        );
                    } else {
                        $content = $response->getContent();
                        $wrapped = new Response($content === false ? '' : $content, $response->getStatusCode());
                        $wrapped->headers->replace($response->headers->all());
                        $response = $wrapped;
                    }
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

        $logger = $this->container->has(LoggerInterface::class)
            ? $this->container->make(LoggerInterface::class)
            : null;

        return new ExceptionHandler($this->isDebug(), $logger);
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
        $cors = CorsMiddleware::fromConfig(ConfigLoader::load(CorsConfig::class));

        $this->pipeline = MiddlewarePipeline::defaults($cors);
        $this->pipeline->setContainer($this->container);
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
     * Application factory method
     */
    public static function create(string $basePath): self
    {
        return new self($basePath);
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
        return (string) $this->env('APP_ENV', 'development');
    }

    /**
     * Check if application is in debug mode
     */
    public function isDebug(): bool
    {
        return (bool) env('APP_DEBUG', false);
    }

}
