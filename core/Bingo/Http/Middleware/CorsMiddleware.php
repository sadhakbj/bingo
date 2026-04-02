<?php

declare(strict_types=1);

namespace Bingo\Http\Middleware;

use Bingo\Contracts\HttpResponse;
use Bingo\Contracts\MiddlewareInterface;
use Bingo\Http\Request;
use Bingo\Http\Response as BingoResponse;
use Config\CorsConfig;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function handle(Request $request, callable $next): HttpResponse
    {
        $origin = $request->headers->get('Origin');

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflight($request, $origin);
        }

        // Process the request normally
        $response = $next ? $next($request) : BingoResponse::json(['message' => 'OK']);

        // Add CORS headers to the response
        return $this->addCorsHeaders($response, $origin);
    }

    private function handlePreflight(Request $request, ?string $origin): HttpResponse
    {
        $response = new BingoResponse('', 204);

        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return new BingoResponse('', 403);
        }

        // Check if method is allowed
        $requestMethod = $request->headers->get('Access-Control-Request-Method');
        if ($requestMethod && !in_array($requestMethod, $this->config['allowed_methods'])) {
            return new BingoResponse('', 403);
        }

        // Check if headers are allowed
        $requestHeaders = $request->headers->get('Access-Control-Request-Headers');
        if ($requestHeaders) {
            $requestedHeaders = array_map('trim', explode(',', $requestHeaders));
            foreach ($requestedHeaders as $header) {
                if (!in_array($header, $this->config['allowed_headers'])) {
                    return new BingoResponse('', 403);
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

    private function addCorsHeaders(SymfonyResponse $response, ?string $origin): HttpResponse
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
     * Build from a typed CorsConfig — all user-configured values are respected.
     */
    public static function fromConfig(CorsConfig $config): self
    {
        return new self([
            'allowed_origins'  => $config->getAllowedOrigins(),
            'allowed_methods'  => array_map('trim', explode(',', $config->allowedMethods)),
            'allowed_headers'  => $config->allowedHeaders === '*'
                ? ['*']
                : array_map('trim', explode(',', $config->allowedHeaders)),
            'exposed_headers'  => $config->exposedHeaders !== ''
                ? array_map('trim', explode(',', $config->exposedHeaders))
                : [],
            'allow_credentials' => $config->supportsCredentials,
            'max_age'           => $config->maxAge,
        ]);
    }

    /**
     * Create CORS middleware with a raw config array (escape hatch).
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }
}
