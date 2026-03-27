<?php

namespace Core\Http\Middleware;

use Core\Http\Request;
use Core\Http\Response;

class RequestIdMiddleware
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'header_name' => 'X-Request-ID',
            'response_header' => 'X-Request-ID',
            'generator' => 'uuid4', // uuid4, uniqid, or callable
        ], $config);
    }

    public function handle(Request $request, ?callable $next = null): Response
    {
        // Check if request already has an ID
        $requestId = $request->headers->get($this->config['header_name']);
        
        if (!$requestId) {
            $requestId = $this->generateId();
        }
        
        // Store request ID for logging and tracing
        $request->attributes->set('request_id', $requestId);
        $request->headers->set($this->config['header_name'], $requestId);
        
        $response = $next ? $next($request) : Response::json(['message' => 'OK']);
        
        // Add request ID to response headers
        if ($this->config['response_header']) {
            $response->headers->set($this->config['response_header'], $requestId);
        }
        
        return $response;
    }
    
    private function generateId(): string
    {
        switch ($this->config['generator']) {
            case 'uuid4':
                return $this->generateUuid4();
            case 'uniqid':
                return uniqid('req_', true);
            default:
                if (is_callable($this->config['generator'])) {
                    return call_user_func($this->config['generator']);
                }
                return uniqid('req_', true);
        }
    }
    
    private function generateUuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    public static function create(array $config = []): self
    {
        return new self($config);
    }
}