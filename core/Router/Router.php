<?php

namespace Core\Router;

use Core\Attributes\Middleware;
use Core\Attributes\Route;
use Core\Http\Request;
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

    public function __construct()
    {
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
        }

        foreach ($methods as $method) {
            // Get all attributes for the method
            $allAttributes = $method->getAttributes();
            $routeAttributes = [];
            $middlewareAttributes = [];
            foreach ($allAttributes as $attr) {
                $instance = $attr->newInstance();
                if ($instance instanceof Route) {
                    $routeAttributes[] = $instance;
                }
                if ($instance instanceof Middleware) {
                    $middlewareAttributes[] = $instance;
                }
            }

            foreach ($routeAttributes as $route) {
                $middlewares = [];
                foreach ($middlewareAttributes as $middlewareInstance) {
                    $middlewares = array_merge($middlewares, $middlewareInstance->middlewares);
                }

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
                } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e2) {
                    // Neither path matched
                    if ($isApiController) {
                        return \Core\Http\Response::json(['error' => 'Not Found'], 404);
                    }
                    return "404 - Not Found";
                } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException $e2) {
                    if ($isApiController) {
                        return \Core\Http\Response::json(['error' => 'Method Not Allowed'], 405);
                    }
                    return "405 - Method Not Allowed";
                }
            } else {
                // Only one path attempted, it didn't match
                if ($isApiController) {
                    return \Core\Http\Response::json(['error' => 'Not Found'], 404);
                }
                return "404 - Not Found";
            }
        } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException $e) {
            if ($isApiController) {
                return \Core\Http\Response::json(['error' => 'Method Not Allowed'], 405);
            }
            return "405 - Method Not Allowed";
        } catch (\Throwable $e) {
            if ($isApiController) {
                return \Core\Http\Response::json(['error' => 'Internal Server Error', 'message' => $e->getMessage()], 500);
            }
            return "500 - Internal Server Error";
        }

        $action = $parameters['_action'];
        $routeName = $parameters['_route_name'];
        unset($parameters['_controller'], $parameters['_action'], $parameters['_route_name'], $parameters['_route']);

        foreach ($this->middlewares[$routeName] ?? [] as $middleware) {
            $middlewareInstance = new $middleware();
            if (method_exists($middlewareInstance, 'handle')) {
                $result = $middlewareInstance->handle();
                if ($result === false) {
                    if ($isApiController) {
                        return \Core\Http\Response::json(['error' => 'Forbidden'], 403);
                    }
                    return "403 - Access Denied";
                }
            }
        }

        $controller = new $controllerClass();
        $reflection = new \ReflectionMethod($controller, $action);
        $args = [];
        
        foreach ($reflection->getParameters() as $param) {
            $value = null;
            $handled = false;
            
            // Check for parameter attributes (NestJS-style)
            foreach ($param->getAttributes() as $attr) {
                $instance = $attr->newInstance();
                
                // #[Body] - Extract DTO from request body
                if ($instance instanceof \Core\Attributes\Body) {
                    $handled = true;
                    $type = $param->getType();
                    if ($type && $type instanceof \ReflectionNamedType) {
                        $dtoClass = $type->getName();
                        if (is_subclass_of($dtoClass, \Core\Data\DataTransferObject::class)) {
                            try {
                                $value = $dtoClass::fromRequest($request);
                            } catch (\Core\Validation\ValidationException $e) {
                                if ($isApiController) {
                                    return \Core\Http\Response::json(['errors' => $e->errors], 422);
                                }
                                return "422 - Validation Failed: " . implode(', ', array_keys($e->errors));
                            }
                        }
                    }
                    break;
                }
                
                // #[Query] - Extract query parameter
                if ($instance instanceof \Core\Attributes\Query) {
                    $handled = true;
                    $key = $instance->key ?? $param->getName();
                    $value = $request->query->get($key);
                    
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
                if ($instance instanceof \Core\Attributes\Param) {
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
                if ($instance instanceof \Core\Attributes\Headers) {
                    $handled = true;
                    $key = $instance->key ?? $param->getName();
                    $value = $request->headers->get($key);
                    break;
                }
                
                // #[UploadedFile] - Extract single uploaded file
                if ($instance instanceof \Core\Attributes\UploadedFile) {
                    $handled = true;
                    $key = $instance->key ?? $param->getName();
                    $value = $request->files->get($key);
                    break;
                }
                
                // #[UploadedFiles] - Extract all uploaded files
                if ($instance instanceof \Core\Attributes\UploadedFiles) {
                    $handled = true;
                    $value = $request->files->all();
                    break;
                }
                
                // #[Request] - Inject request object
                if ($instance instanceof \Core\Attributes\Request) {
                    $handled = true;
                    $value = $request;
                    break;
                }
            }
            
            // Fallback to existing logic if no attributes
            if (!$handled) {
                $type = $param->getType();
                if ($type && $type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    if ($typeName === Request::class) {
                        $value = $request;
                    } elseif (is_subclass_of($typeName, \Core\Validation\ValidatedRequest::class)) {
                        try {
                            $value = $typeName::createFromRequest($request);
                        } catch (\Core\Validation\ValidationException $e) {
                            if ($isApiController) {
                                return \Core\Http\Response::json(['errors' => $e->errors], 422);
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

        try {
            $result = $reflection->invokeArgs($controller, $args);
        } catch (\Throwable $e) {
            if ($isApiController) {
                return \Core\Http\Response::json([
                    'error' => 'Internal Server Error', 
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ], 500);
            }
            return "500 - Internal Server Error: " . $e->getMessage();
        }

        // Enforce explicit Response return for ApiController
        if ($isApiController && !$result instanceof \Core\Http\Response) {
            throw new \RuntimeException('ApiController methods must return a Response object. Use Response::json().');
        }
        return $result;
    }
}
