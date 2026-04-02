<?php

declare(strict_types=1);

namespace Bingo;

use Bingo\Bootstrap\ProviderBootstrapper;
use Bingo\Container\Container;
use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Contracts\HttpResponse;
use Bingo\Exceptions\ExceptionHandler;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\Http\Router\Router;
use Bingo\Http\StreamedResponse as BingoStreamedResponse;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;
use Bingo\Discovery\DiscoveryManager;

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

        $this->container = new Container();
        $this->router    = new Router($this->container);
        $this->pipeline  = MiddlewarePipeline::create($this->container);

        // Make router and pipeline injectable so providers can receive them
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(MiddlewarePipeline::class, $this->pipeline);

        $discovered = $this->bootDiscovery();

        new ProviderBootstrapper(
            container: $this->container,
            bindings:  $discovered['bindings']  ?? [],
            providers: $discovered['providers'] ?? [],
        )->boot();

        if (!empty($discovered['controllers'])) {
            $this->router->registerFromCache($discovered['controllers']);
        }

        if (!empty($discovered['commands'])) {
            $this->discoveredCommands = $discovered['commands'];
        }
    }

    /**
     * Load the discovery cache (or rebuild in dev when files change).
     * Returns the full discovered metadata array.
     */
    private function bootDiscovery(): array
    {
        $manager = new DiscoveryManager(
            cacheDir:      base_path('storage/framework/discovery'),
            appPath:       base_path('app'),
            coreBingoPath: __DIR__,
            isProduction:  env('APP_ENV', 'development') === 'production',
        );

        return $manager->load();
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
