<?php

declare(strict_types=1);

namespace Bingo\Http\Middleware;

use Bingo\Container\Container;
use Bingo\Contracts\HttpResponse;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\Http\ResponseNormalizer;

class MiddlewarePipeline
{
    private array      $middleware       = [];
    private array      $globalMiddleware = [];
    private ?Container $container        = null;
    private ResponseNormalizer $responseNormalizer;

    public function __construct()
    {
        $this->responseNormalizer = new ResponseNormalizer();
    }

    /**
     * Add global middleware that runs on all requests.
     * Accepts a class-name string, an already-instantiated object, or a callable.
     * Resolution is deferred to request time so that the DI container is available.
     */
    public function addGlobal($middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Add route-specific middleware (resolved lazily at request time).
     */
    public function add($middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Process request through middleware pipeline
     */
    public function process(Request $request, ?callable $finalHandler = null): HttpResponse
    {
        $middlewareStack = array_merge($this->globalMiddleware, $this->middleware);

        if (empty($middlewareStack)) {
            return $this->normalizeResult(
                $finalHandler ? $finalHandler($request) : Response::json(['message' => 'OK']),
            );
        }

        return $this->executeMiddleware($middlewareStack, 0, $request, $finalHandler);
    }

    /**
     * Execute middleware recursively
     */
    private function executeMiddleware(
        array     $middlewareStack,
        int       $index,
        Request   $request,
        ?callable $finalHandler,
    ): HttpResponse {
        // If we've reached the end of middleware stack, call the final handler
        if ($index >= count($middlewareStack)) {
            return $this->normalizeResult(
                $finalHandler ? $finalHandler($request) : Response::json(['message' => 'OK']),
            );
        }

        // Resolve lazily so DI container bindings are available at request time
        $middleware = $this->resolveMiddleware($middlewareStack[$index]);

        // Create next function that calls the next middleware in the stack
        $next = function (Request $req) use ($middlewareStack, $index, $finalHandler) {
            return $this->executeMiddleware($middlewareStack, $index + 1, $req, $finalHandler);
        };

        // Execute current middleware — let exceptions propagate to Application::handle()
        if (is_callable($middleware)) {
            return $this->normalizeResult($middleware($request, $next));
        }

        if (is_object($middleware) && method_exists($middleware, 'handle')) {
            return $this->normalizeResult($middleware->handle($request, $next));
        }

        throw new \InvalidArgumentException('Middleware must be callable or have a handle method');
    }

    /**
     * Resolve middleware to callable form
     */
    private function resolveMiddleware($middleware): callable|object
    {
        if (is_string($middleware) && class_exists($middleware)) {
            return $this->container !== null
                ? $this->container->make($middleware)
                : new $middleware();
        }

        if (is_callable($middleware)) {
            return $middleware;
        }

        if (is_object($middleware)) {
            return $middleware;
        }

        throw new \InvalidArgumentException('Invalid middleware type');
    }

    /**
     * Create a new middleware pipeline
     */
    public static function create(?Container $container = null, ?ResponseNormalizer $responseNormalizer = null): self
    {
        $instance            = new self();
        $instance->container = $container;
        if ($responseNormalizer !== null) {
            $instance->responseNormalizer = $responseNormalizer;
        }
        return $instance;
    }

    public function setContainer(?Container $container): self
    {
        $this->container = $container;
        return $this;
    }

    public function setResponseNormalizer(ResponseNormalizer $responseNormalizer): self
    {
        $this->responseNormalizer = $responseNormalizer;
        return $this;
    }

    /**
     * Build the default middleware stack.
     * The caller is responsible for constructing the CorsMiddleware instance
     * (typically via CorsMiddleware::fromConfig()) so all user config is respected.
     */
    public static function defaults(CorsMiddleware $cors): self
    {
        $pipeline = new self();

        $pipeline->addGlobal($cors);
        $pipeline->addGlobal(BodyParserMiddleware::json());
        $pipeline->addGlobal(CompressionMiddleware::create());
        $pipeline->addGlobal(SecurityHeadersMiddleware::create());
        $pipeline->addGlobal(RequestIdMiddleware::create());

        return $pipeline;
    }

    /**
     * Get middleware count for debugging
     */
    public function count(): int
    {
        return count($this->globalMiddleware) + count($this->middleware);
    }

    /**
     * Clear all middleware
     */
    public function clear(): self
    {
        $this->middleware       = [];
        $this->globalMiddleware = [];
        return $this;
    }

    /**
     * Express.js style use() method
     */
    public function use($middleware): self
    {
        return $this->addGlobal($middleware);
    }

    private function normalizeResult(mixed $result): HttpResponse
    {
        return $this->responseNormalizer->normalize($result);
    }
}
