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
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\Http\Router\Router;
use Bingo\Http\StreamedResponse as BingoStreamedResponse;
use Config\AppConfig;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

class Application
{
    public readonly string $basePath;
    public private(set) Router $router;

    public string $environment { get => $this->appConfig->env; }
    public bool   $debug       { get => $this->appConfig->debug; }

    private Container $container;
    private MiddlewarePipeline $pipeline;
    private array $controllers = [];
    private ?ExceptionHandlerInterface $customExceptionHandler = null;
    private array $discoveredCommands = [];
    private AppConfig $appConfig;

    /**
     * @throws \ReflectionException
     */
    public function __construct(string $basePath)
    {
        $this->basePath  = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->loadEnvironmentVariables();
        $this->appConfig = ConfigLoader::load(AppConfig::class);

        $this->container = new Container();
        $this->router    = new Router($this->container);
        $this->pipeline  = MiddlewarePipeline::create($this->container);

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

    private function bootDiscovery(): array
    {
        $manager = new DiscoveryManager(
            cacheDir:      base_path('storage/framework/discovery'),
            appPath:       base_path('app'),
            coreBingoPath: __DIR__,
            isProduction:  $this->appConfig->env === 'production',
        );

        return $manager->load();
    }

    private function loadEnvironmentVariables(): void
    {
        Dotenv::createImmutable($this->basePath)->load();
    }

    public function use($middleware): self
    {
        $this->pipeline->use($middleware);
        return $this;
    }

    public function controller(string $controllerClass): self
    {
        $this->controllers[] = $controllerClass;
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
        $this->container->compile();
        $request  = Request::createFromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    public function singleton(string $abstract, ?string $concrete = null): self
    {
        $this->container->singleton($abstract, $concrete);
        return $this;
    }

    public function bind(string $abstract, ?string $concrete = null): self
    {
        $this->container->bind($abstract, $concrete);
        return $this;
    }

    public function instance(string $abstract, object $instance): self
    {
        $this->container->instance($abstract, $instance);
        return $this;
    }

    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    public static function create(string $basePath): self
    {
        return new self($basePath);
    }

    public function getDiscoveredCommands(): array
    {
        return $this->discoveredCommands;
    }
}