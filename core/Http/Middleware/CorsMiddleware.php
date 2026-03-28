<?php

declare(strict_types=1);

namespace Core\Http\Middleware;

use Core\Contracts\MiddlewareInterface;
use Core\Http\Request;
use Core\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
            'exposed_headers' => [],
            'allow_credentials' => false,
            'max_age' => 86400, // 24 hours
        ], $config);
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->headers->get('Origin');
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflight($request, $origin);
        }
        
        // Process the request normally
        $response = $next ? $next($request) : Response::json(['message' => 'OK']);
        
        // Add CORS headers to the response
        return $this->addCorsHeaders($response, $origin);
    }

    private function handlePreflight(Request $request, ?string $origin): Response
    {
        $response = new Response('', 204);
        
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return new Response('', 403);
        }
        
        // Check if method is allowed
        $requestMethod = $request->headers->get('Access-Control-Request-Method');
        if ($requestMethod && !in_array($requestMethod, $this->config['allowed_methods'])) {
            return new Response('', 403);
        }
        
        // Check if headers are allowed
        $requestHeaders = $request->headers->get('Access-Control-Request-Headers');
        if ($requestHeaders) {
            $requestedHeaders = array_map('trim', explode(',', $requestHeaders));
            foreach ($requestedHeaders as $header) {
                if (!in_array($header, $this->config['allowed_headers'])) {
                    return new Response('', 403);
                }
            }
        }
        
        // Add preflight headers
        $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']));
        $response->headers->set('Access-Control-Max-Age', (string) $this->config['max_age']);
        
        if ($this->config['allow_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        
        return $response;
    }

    private function addCorsHeaders(Response $response, ?string $origin): Response
    {
        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        }
        
        if (!empty($this->config['exposed_headers'])) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->config['exposed_headers']));
        }
        
        if ($this->config['allow_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        
        // Add Vary header to indicate that the response varies with origin
        $response->headers->set('Vary', 'Origin');
        
        return $response;
    }

    private function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return true; // Allow requests without origin (like Postman)
        }
        
        $allowedOrigins = $this->config['allowed_origins'];
        
        // Check for wildcard
        if (in_array('*', $allowedOrigins)) {
            return true;
        }
        
        // Check exact match
        if (in_array($origin, $allowedOrigins)) {
            return true;
        }
        
        // Check pattern matching (e.g., *.example.com)
        foreach ($allowedOrigins as $allowedOrigin) {
            if (strpos($allowedOrigin, '*') !== false) {
                $pattern = str_replace('*', '.*', $allowedOrigin);
                if (preg_match('/^' . $pattern . '$/', $origin)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Create CORS middleware with Express.js style configuration
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }
    
    /**
     * Default CORS configuration for development
     */
    public static function development(): self
    {
        return new self([
            'allowed_origins' => ['*'],
            'allow_credentials' => false,
        ]);
    }
    
    /**
     * Restrictive CORS configuration for production
     */
    public static function production(array $allowedOrigins): self
    {
        return new self([
            'allowed_origins' => $allowedOrigins,
            'allow_credentials' => true,
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
            'max_age' => 3600, // 1 hour
        ]);
    }
}