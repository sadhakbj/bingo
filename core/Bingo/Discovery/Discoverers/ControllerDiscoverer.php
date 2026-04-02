<?php

declare(strict_types=1);

namespace Bingo\Discovery\Discoverers;

use Bingo\Attributes\Middleware;
use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Delete;
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Head;
use Bingo\Attributes\Route\Options;
use Bingo\Attributes\Route\Patch;
use Bingo\Attributes\Route\Post;
use Bingo\Attributes\Route\Put;
use Bingo\Attributes\Route\Route;
use Bingo\Attributes\Route\Throttle;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

/**
 * Discovers controllers by scanning app/Http/Controllers for classes with
 * route attributes (#[ApiController], #[Get], #[Post], etc.).
 *
 * Extracts route metadata including paths, HTTP methods, middleware, and
 * throttle limits. Parameter bindings are resolved at runtime via reflection.
 */
class ControllerDiscoverer implements DiscovererInterface
{
    private const ROUTE_ATTRIBUTES = [
        Get::class,
        Post::class,
        Put::class,
        Patch::class,
        Delete::class,
        Head::class,
        Options::class,
        Route::class,
    ];

    public function __construct(private readonly string $appPath) {}

    public function type(): string
    {
        return 'controllers';
    }

    public function discover(): array
    {
        $controllers = [];
        $controllerPath = $this->appPath . '/Http/Controllers';

        if (!is_dir($controllerPath)) {
            return [];
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllerPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->getClassFromFile($file->getPathname());
            if (!$class || !class_exists($class)) {
                continue;
            }

            $controllerData = $this->analyzeController($class);
            if ($controllerData) {
                $controllers[$class] = $controllerData;
            }
        }

        return $controllers;
    }

    /**
     * Analyze a controller class and extract all route metadata.
     */
    private function analyzeController(string $class): ?array
    {
        $reflection = new ReflectionClass($class);

        // Get class-level metadata
        $prefix = $this->getPrefix($reflection);
        $classMiddleware = $this->getMiddleware($reflection);
        $classThrottles = $this->getThrottles($reflection);

        $routes = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isStatic()) {
                continue;
            }

            $routeData = $this->analyzeMethod($method);
            if ($routeData) {
                $routes[] = $routeData;
            }
        }

        // If no routes found, skip this controller
        if (empty($routes)) {
            return null;
        }

        return [
            'class' => $class,
            'prefix' => $prefix,
            'class_middleware' => $classMiddleware,
            'class_throttles' => $classThrottles,
            'routes' => $routes,
        ];
    }

    /**
     * Analyze a controller method for route attributes.
     */
    private function analyzeMethod(ReflectionMethod $method): ?array
    {
        $httpMethod = null;
        $path = null;

        // Find route attribute (Get, Post, Put, etc.)
        foreach (self::ROUTE_ATTRIBUTES as $attrClass) {
            $attrs = $method->getAttributes($attrClass);
            if (!empty($attrs)) {
                $instance = $attrs[0]->newInstance();
                $httpMethod = $this->getHttpMethod($attrClass, $instance);
                $path = $instance->path ?? '/';
                break;
            }
        }

        if (!$httpMethod) {
            return null;
        }

        return [
            'method' => $httpMethod,
            'path' => $path,
            'action' => $method->getName(),
            'middleware' => $this->getMiddleware($method),
            'throttles' => $this->getThrottles($method),
        ];
    }

    /**
     * Extract route prefix from #[ApiController] attribute.
     */
    private function getPrefix(ReflectionClass $reflection): string
    {
        $attrs = $reflection->getAttributes(ApiController::class);
        if (empty($attrs)) {
            return '';
        }

        $instance = $attrs[0]->newInstance();
        return $instance->prefix ?? '';
    }

    /**
     * Extract middleware from #[Middleware] attribute.
     */
    private function getMiddleware(ReflectionClass|ReflectionMethod $reflection): array
    {
        $attrs = $reflection->getAttributes(Middleware::class);
        if (empty($attrs)) {
            return [];
        }

        $instance = $attrs[0]->newInstance();
        return $instance->middlewares; // Note: property is 'middlewares' (plural)
    }

    /**
     * Extract all throttle limits from #[Throttle] attributes.
     * Supports multiple throttles on both classes and methods.
     */
    private function getThrottles(ReflectionClass|ReflectionMethod $reflection): array
    {
        $attrs = $reflection->getAttributes(Throttle::class);
        if (empty($attrs)) {
            return [];
        }

        $throttles = [];
        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
            $throttles[] = [
                'requests' => $instance->requests,
                'per' => $instance->per,
            ];
        }

        return $throttles;
    }

    /**
     * Map attribute class to HTTP method string.
     */
    private function getHttpMethod(string $attrClass, object $instance): string
    {
        return match ($attrClass) {
            Get::class => 'GET',
            Post::class => 'POST',
            Put::class => 'PUT',
            Patch::class => 'PATCH',
            Delete::class => 'DELETE',
            Head::class => 'HEAD',
            Options::class => 'OPTIONS',
            Route::class => strtoupper($instance->method ?? 'GET'),
            default => 'GET',
        };
    }

    /**
     * Extract fully-qualified class name from a PHP file.
     *
     * Uses regex to parse namespace and class declarations.
     */
    private function getClassFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        // Extract namespace
        if (!preg_match('/namespace\s+([^;]+);/m', $contents, $matches)) {
            return null;
        }
        $namespace = $matches[1];

        // Extract class name
        if (!preg_match('/class\s+(\w+)/m', $contents, $matches)) {
            return null;
        }
        $className = $matches[1];

        return $namespace . '\\' . $className;
    }
}
