<?php

declare(strict_types=1);

namespace Bingo;

use Bingo\Bootstrap\ProviderBootstrapper;
use Bingo\Config\ConfigLoader;
use Bingo\Container\Container;
use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Contracts\HttpResponse;
use Bingo\Discovery\DiscoveryManager;
use Bingo\Exceptions\ExceptionHandler;
use Bingo\Http\HttpKernel;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Request;
use Bingo\Http\Router\Router;
use Config\AppConfig;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;

class Application
{
    public readonly string $basePath;
    public private(set) Router $router;

    public string $environment { get => $this->appConfig->env; }
    public bool   $debug       { get => $this->appConfig->debug; }

    private Container $container;
    private MiddlewarePipeline $pipeline;
    private ?ExceptionHandlerInterface $customExceptionHandler = null;
    private array $discoveredCommands = [];
    private AppConfig $appConfig;
    private HttpKernel $httpKernel;
    private bool $booted = false;

    /**
     * @throws \ReflectionException
     */
    public function __construct(string $basePath)
    {
        $this->basePath  = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->loadEnvironmentVariables();
        $this->appConfig = ConfigLoader::load(AppConfig::class);
        $this->initializeCoreServices();
    }

    private function initializeCoreServices(): void
    {
        $this->container = new Container();
        $this->router    = new Router($this->container);
        $this->pipeline  = MiddlewarePipeline::create($this->container);
        $this->httpKernel = new HttpKernel(
            $this->pipeline,
            $this->router,
            $this->resolveExceptionHandler(...),
        );

        $this->container->instance(Router::class, $this->router);
        $this->container->instance(MiddlewarePipeline::class, $this->pipeline);
        $this->container->instance(HttpKernel::class, $this->httpKernel);
    }

    private function bootFromDiscovery(): void
    {
        $discovered = $this->bootDiscovery();

        new ProviderBootstrapper(
            container: $this->container,
            bindings:  $discovered['bindings']  ?? [],
            providers: $discovered['providers'] ?? [],
        )->boot();

        $this->registerDiscoveredControllers($discovered['controllers'] ?? []);
        $this->storeDiscoveredCommands($discovered['commands'] ?? []);
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $this->bootFromDiscovery();
        $this->booted = true;

        return $this;
    }

    private function registerDiscoveredControllers(array $controllers): void
    {
        if (!empty($controllers)) {
            $this->router->registerFromCache($controllers);
        }
    }

    private function storeDiscoveredCommands(array $commands): void
    {
        $this->discoveredCommands = $commands;
    }

    private function bootDiscovery(): array
    {
        $manager = new DiscoveryManager(
            cacheDir:      $this->frameworkPath('discovery'),
            appPath:       $this->appPath(),
            coreBingoPath: __DIR__,
            isProduction:  $this->appConfig->env === 'production',
        );

        return $manager->load();
    }

    private function loadEnvironmentVariables(): void
    {
        // Local development typically uses a .env file, but container platforms
        // like Docker and Kubernetes inject process env vars directly.
        Dotenv::createImmutable($this->basePath)->safeLoad();
    }

    public function use($middleware): self
    {
        $this->assertNotBooted(__FUNCTION__);
        $this->pipeline->use($middleware);
        return $this;
    }

    public function controller(string $controllerClass): self
    {
        $this->assertNotBooted(__FUNCTION__);
        $this->router->registerController($controllerClass);
        return $this;
    }

    public function controllers(array $controllers): self
    {
        foreach ($controllers as $controller) {
            $this->controller($controller);
        }
        return $this;
    }

    public function handle(Request $request): HttpResponse
    {
        $this->boot();
        return $this->httpKernel->handle($request);
    }

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

        return new ExceptionHandler($this->debug, $logger);
    }

    public function run(): void
    {
        $this->boot();
        $this->container->compile();
        $request  = Request::createFromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    public function singleton(string $abstract, ?string $concrete = null): self
    {
        $this->assertNotBooted(__FUNCTION__);
        $this->container->singleton($abstract, $concrete);
        $this->container->protect($abstract);
        return $this;
    }

    public function bind(string $abstract, ?string $concrete = null): self
    {
        $this->assertNotBooted(__FUNCTION__);
        $this->container->bind($abstract, $concrete);
        $this->container->protect($abstract);
        return $this;
    }

    public function instance(string $abstract, object $instance): self
    {
        $this->assertNotBooted(__FUNCTION__);
        $this->container->instance($abstract, $instance);
        $this->container->protect($abstract);
        return $this;
    }

    public function make(string $abstract): mixed
    {
        $this->boot();
        return $this->container->make($abstract);
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    public function appPath(string $path = ''): string
    {
        return $this->basePath('app' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath('bootstrap' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    public function frameworkPath(string $path = ''): string
    {
        return $this->storagePath('framework' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    public static function create(string $basePath): self
    {
        return new self($basePath);
    }

    public function getDiscoveredCommands(): array
    {
        $this->boot();
        return $this->discoveredCommands;
    }

    private function assertNotBooted(string $method): void
    {
        if ($this->booted) {
            throw new \LogicException(
                "Cannot call {$method}() after the application has booted."
            );
        }
    }
}
