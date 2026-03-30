<?php

declare(strict_types=1);

namespace Core\Router;

use Core\Attributes\Middleware;
use Core\Attributes\Route\Body;
use Core\Attributes\Route\Headers;
use Core\Attributes\Route\Param;
use Core\Attributes\Route\Query;
use Core\Attributes\Route\Request as RequestAttr;
use Core\Attributes\Route\Route;
use Core\Attributes\Route\UploadedFile;
use Core\Attributes\Route\UploadedFiles;
use Core\Container\Container;
use Core\Exceptions\MethodNotAllowedException;
use Core\Exceptions\NotFoundException;
use Core\Http\Middleware\MiddlewarePipeline;
use Core\Http\Request;
use Core\Http\Response;
use Core\Validation\ValidationException;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    private RouteCollection $routes;
    private array $middlewares = [];
    private array $apiControllerPrefixes = [];

    public function __construct(
        private readonly ?Container $container = null
    ) {
        $this->routes = new RouteCollection();
    }

    public function registerController(string $controllerClass): void
    {
        $reflectionClass = new ReflectionClass($controllerClass);
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        // Check for ApiController attribute and get prefix if present
        $apiControllerAttr = $reflectionClass->getAttributes(\Core\Attributes\ApiController::class);
        $prefix = '';
        if ($apiControllerAttr) {
            $apiController = $apiControllerAttr[0]->newInstance();
            $prefix = rtrim($apiController->prefix ?? '', '/');
            if ($prefix !== '') {
                $this->apiControllerPrefixes[] = $prefix;
            }
        }

        // Collect class-level middleware — applies to every route in this controller
        $classMiddlewares = [];
        foreach ($reflectionClass->getAttributes(Middleware::class) as $attr) {
            $classMiddlewares = array_merge($classMiddlewares, $attr->newInstance()->middlewares);
        }

        foreach ($methods as $method) {
            // Get all attributes for the method
            $allAttributes = $method->getAttributes();
            $routeAttributes = [];
            $methodMiddlewares = [];
            foreach ($allAttributes as $attr) {
                $instance = $attr->newInstance();
                if ($instance instanceof Route) {
                    $routeAttributes[] = $instance;
                }
                if ($instance instanceof Middleware) {
                    $methodMiddlewares = array_merge($methodMiddlewares, $instance->middlewares);
                }
            }

            foreach ($routeAttributes as $route) {
                // Class-level middleware runs first (outer), method-level runs second (inner)
                $middlewares = array_merge($classMiddlewares, $methodMiddlewares);

                $routeName = $controllerClass . '@' . $method->getName();
                $this->middlewares[$routeName] = $middlewares;

                // Prepend prefix if present
                $fullPath = $prefix . $route->path;
                if ($fullPath === '') $fullPath = '/';
                $fullPath = preg_replace('#//+#', '/', $fullPath); // Clean up double slashes

                $symfonyRoute = new SymfonyRoute(
                    $fullPath,
                    [
                        '_controller' => $controllerClass,
                        '_action' => $method->getName(),
                        '_route_name' => $routeName
                    ],
                    [],
                    [],
                    '',
                    [],
                    [$route->method]
                );
                $this->routes->add($routeName, $symfonyRoute);
            }
        }
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function getMiddlewaresForRoute(string $routeName): array
    {
        return $this->middlewares[$routeName] ?? [];
    }

    private function isApiPath(string $path): bool
    {
        foreach ($this->apiControllerPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function dispatch(Request $request)
    {
        $context = new RequestContext();
        $context->fromRequest($request);
        $matcher = new UrlMatcher($this->routes, $context);

        $controllerClass = null;
        $isApiController = false;
        
        // Normalize path by removing trailing slash (except for root path)
        // This allows both /users and /users/ to match the same route
        $pathInfo = $request->getPathInfo();
        $normalizedPath = $pathInfo;
        if ($pathInfo !== '/' && str_ends_with($pathInfo, '/')) {
            $normalizedPath = rtrim($pathInfo, '/');
        }
        
        try {
            // Try to match with normalized path first
            $parameters = $matcher->match($normalizedPath);
            $controllerClass = $parameters['_controller'] ?? null;
            if ($controllerClass) {
                $reflectionClass = new ReflectionClass($controllerClass);
                $isApiController = !empty($reflectionClass->getAttributes(\Core\Attributes\ApiController::class));
            }
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
            // If normalized path didn't match and it's different from original, try original path
            if ($normalizedPath !== $pathInfo) {
                try {
                    $parameters = $matcher->match($pathInfo);
                    $controllerClass = $parameters['_controller'] ?? null;
                    if ($controllerClass) {
                        $reflectionClass = new ReflectionClass($controllerClass);
                        $isApiController = !empty($reflectionClass->getAttributes(\Core\Attributes\ApiController::class));
                    }
                } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
                    if ($this->isApiPath($normalizedPath)) {
                        throw new NotFoundException();
                    }

                    return "404 - Not Found";
                } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException) {
                    if ($this->isApiPath($normalizedPath)) {
                        throw new MethodNotAllowedException();
                    }

                    return "405 - Method Not Allowed";
                }
            } else {
                if ($this->isApiPath($normalizedPath)) {
                    throw new NotFoundException();
                }

                return "404 - Not Found";
            }
        } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException) {
            if ($this->isApiPath($normalizedPath)) {
                throw new MethodNotAllowedException();
            }

            return "405 - Method Not Allowed";
        } catch (\Throwable $e) {
            throw $e;
        }

        $action = $parameters['_action'];
        $routeName = $parameters['_route_name'];
        unset($parameters['_controller'], $parameters['_action'], $parameters['_route_name'], $parameters['_route']);

        $routeMiddlewares = $this->middlewares[$routeName] ?? [];
        $controller = $this->container !== null
            ? $this->container->make($controllerClass)
            : new $controllerClass();
        $reflection       = new \ReflectionMethod($controller, $action);

        // Build the final handler: resolve args then invoke the controller method
        $finalHandler = function (Request $req) use (
            $controller, $reflection, $parameters, $isApiController
        ) {
            $args = [];

            foreach ($reflection->getParameters() as $param) {
            $value = null;
            $handled = false;
            
            // Check for parameter attributes (NestJS-style)
            foreach ($param->getAttributes() as $attr) {
                $instance = $attr->newInstance();
                
                // #[Body] - Extract DTO from request body
                if ($instance instanceof Body) {
                    $handled = true;
                    $type = $param->getType();
                    if ($type && $type instanceof \ReflectionNamedType) {
                        $dtoClass = $type->getName();
                        if (is_subclass_of($dtoClass, \Core\Data\DataTransferObject::class)) {
                            try {
                                $value = $dtoClass::fromRequest($req);
                            } catch (ValidationException $e) {
                                if ($isApiController) {
                                    throw $e;
                                }

                                return "422 - Validation Failed: " . implode(', ', array_keys($e->errors));
                            }
                        }
                    }
                    break;
                }
                
                // #[Query] - Extract query parameter
                if ($instance instanceof Query) {
                    $handled = true;
                    $key = $instance->key ?? $param->getName();
                    $value = $req->query->get($key);
                    
                    // Type cast if needed
                    $type = $param->getType();
                    if ($type && $type instanceof \ReflectionNamedType && $value !== null) {
                        $typeName = $type->getName();
                        if ($typeName === 'int') {
                            $value = (int) $value;
                        } elseif ($typeName === 'float') {
                            $value = (float) $value;
                        } elseif ($typeName === 'bool') {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        }
                    }
                    break;
                }
                
                // #[Param] - Extract route parameter
                if ($instance instanceof Param) {
                    $handled = true;
                    $value = $parameters[$instance->key] ?? null;
                    
                    // Type cast if needed
                    $type = $param->getType();
                    if ($type && $type instanceof \ReflectionNamedType && $value !== null) {
                        $typeName = $type->getName();
                        if ($typeName === 'int') {
                            $value = (int) $value;
                        } elseif ($typeName === 'float') {
                            $value = (float) $value;
                        }
                    }
                    break;
                }
                
                // #[Headers] - Extract header value
                if ($instance instanceof Headers) {
                    $handled = true;
                    $key = $instance->key ?? $param->getName();
                    $value = $req->headers->get($key);
                    break;
                }
                
                // #[UploadedFile] - Extract single uploaded file
                if ($instance instanceof UploadedFile) {
                    $handled = true;
                    $key = $instance->key ?? $param->getName();
                    $value = $req->files->get($key);
                    break;
                }
                
                // #[UploadedFiles] - Extract all uploaded files
                if ($instance instanceof UploadedFiles) {
                    $handled = true;
                    $value = $req->files->all();
                    break;
                }
                
                // #[Request] - Inject request object
                if ($instance instanceof RequestAttr) {
                    $handled = true;
                    $value = $req;
                    break;
                }
            }
            
            // Fallback to existing logic if no attributes
            if (!$handled) {
                $type = $param->getType();
                if ($type && $type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    if ($typeName === Request::class) {
                        $value = $req;
                    } elseif (is_subclass_of($typeName, \Core\Validation\ValidatedRequest::class)) {
                        try {
                            $value = $typeName::createFromRequest($req);
                        } catch (ValidationException $e) {
                            if ($isApiController) {
                                throw $e;
                            }

                            return "422 - Validation Failed: " . implode(', ', array_keys($e->errors));
                        }
                    } else {
                        $value = $parameters[$param->getName()] ?? null;
                    }
                } else {
                    $value = $parameters[$param->getName()] ?? null;
                }
            }
            
                $args[] = $value;
            }

            $result = $reflection->invokeArgs($controller, $args);

            // Enforce explicit Response return for ApiController
            if ($isApiController && !$result instanceof Response) {
                throw new \RuntimeException(
                    'ApiController methods must return a Response object. Use Response::json().'
                );
            }

            return $result instanceof Response ? $result : new Response((string) $result);
        };

        // Run route-level middleware through a proper $next pipeline
        if (!empty($routeMiddlewares)) {
            $pipeline = MiddlewarePipeline::create($this->container);
            foreach ($routeMiddlewares as $middlewareClass) {
                $pipeline->add($middlewareClass);
            }
            return $pipeline->process($request, $finalHandler);
        }

        return $finalHandler($request);
    }
}
