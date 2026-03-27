<?php

namespace Core\Http\Middleware;

use Core\Http\Request;
use Core\Http\Response;

class MiddlewarePipeline
{
    private array $middleware = [];
    private array $globalMiddleware = [];

    /**
     * Add global middleware that runs on all requests
     */
    public function addGlobal($middleware): self
    {
        $this->globalMiddleware[] = $this->resolveMiddleware($middleware);
        return $this;
    }

    /**
     * Add route-specific middleware
     */
    public function add($middleware): self
    {
        $this->middleware[] = $this->resolveMiddleware($middleware);
        return $this;
    }

    /**
     * Process request through middleware pipeline
     */
    public function process(Request $request, ?callable $finalHandler = null): Response
    {
        $middlewareStack = array_merge($this->globalMiddleware, $this->middleware);
        
        if (empty($middlewareStack)) {
            return $finalHandler ? $finalHandler($request) : Response::json(['message' => 'OK']);
        }

        return $this->executeMiddleware($middlewareStack, 0, $request, $finalHandler);
    }

    /**
     * Execute middleware recursively
     */
    private function executeMiddleware(array $middlewareStack, int $index, Request $request, ?callable $finalHandler): Response
    {
        // If we've reached the end of middleware stack, call the final handler
        if ($index >= count($middlewareStack)) {
            return $finalHandler ? $finalHandler($request) : Response::json(['message' => 'OK']);
        }

        $middleware = $middlewareStack[$index];
        
        // Create next function that calls the next middleware in the stack
        $next = function(Request $req) use ($middlewareStack, $index, $finalHandler) {
            return $this->executeMiddleware($middlewareStack, $index + 1, $req, $finalHandler);
        };

        // Execute current middleware
        try {
            if (is_callable($middleware)) {
                return $middleware($request, $next);
            } elseif (is_object($middleware) && method_exists($middleware, 'handle')) {
                return $middleware->handle($request, $next);
            } else {
                throw new \InvalidArgumentException('Middleware must be callable or have a handle method');
            }
        } catch (\Exception $e) {
            // Convert exceptions to error responses
            return Response::json([
                'error' => 'Middleware error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve middleware to callable form
     */
    private function resolveMiddleware($middleware): callable|object
    {
        if (is_string($middleware) && class_exists($middleware)) {
            return new $middleware();
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
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create default API middleware pipeline (like Express.js defaults)
     */
    public static function defaultApi(): self
    {
        $pipeline = new self();
        
        // Add common API middleware
        $pipeline->addGlobal(CorsMiddleware::development());
        $pipeline->addGlobal(BodyParserMiddleware::json());
        $pipeline->addGlobal(CompressionMiddleware::create());
        $pipeline->addGlobal(SecurityHeadersMiddleware::create());
        $pipeline->addGlobal(RequestIdMiddleware::create());

        return $pipeline;
    }

    /**
     * Create production API middleware pipeline
     */
    public static function productionApi(array $corsConfig = []): self
    {
        $pipeline = new self();
        
        // Add production middleware with tighter security
        $pipeline->addGlobal(CorsMiddleware::production($corsConfig['allowed_origins'] ?? []));
        $pipeline->addGlobal(BodyParserMiddleware::production());
        $pipeline->addGlobal(CompressionMiddleware::create(['level' => 6]));
        $pipeline->addGlobal(SecurityHeadersMiddleware::production());
        $pipeline->addGlobal(RequestIdMiddleware::create());
        $pipeline->addGlobal(RateLimitMiddleware::create());

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
        $this->middleware = [];
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
}