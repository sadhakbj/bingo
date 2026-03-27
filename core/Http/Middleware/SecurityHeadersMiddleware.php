<?php

namespace Core\Http\Middleware;

use Core\Http\Request;
use Core\Http\Response;

class SecurityHeadersMiddleware
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'hsts' => [
                'max_age' => 31536000, // 1 year
                'include_subdomains' => true,
                'preload' => false
            ],
            'csp' => "default-src 'self'",
            'x_frame_options' => 'DENY',
            'x_content_type_options' => 'nosniff',
            'x_xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy' => 'geolocation=(), microphone=(), camera=()'
        ], $config);
    }

    public function handle(Request $request, ?callable $next = null): Response
    {
        $response = $next ? $next($request) : Response::json(['message' => 'OK']);
        
        // HSTS (HTTP Strict Transport Security)
        if ($request->isSecure() && $this->config['hsts']) {
            $hstsValue = 'max-age=' . $this->config['hsts']['max_age'];
            if ($this->config['hsts']['include_subdomains']) {
                $hstsValue .= '; includeSubDomains';
            }
            if ($this->config['hsts']['preload']) {
                $hstsValue .= '; preload';
            }
            $response->headers->set('Strict-Transport-Security', $hstsValue);
        }
        
        // Content Security Policy
        if ($this->config['csp']) {
            $response->headers->set('Content-Security-Policy', $this->config['csp']);
        }
        
        // X-Frame-Options
        if ($this->config['x_frame_options']) {
            $response->headers->set('X-Frame-Options', $this->config['x_frame_options']);
        }
        
        // X-Content-Type-Options
        if ($this->config['x_content_type_options']) {
            $response->headers->set('X-Content-Type-Options', $this->config['x_content_type_options']);
        }
        
        // X-XSS-Protection
        if ($this->config['x_xss_protection']) {
            $response->headers->set('X-XSS-Protection', $this->config['x_xss_protection']);
        }
        
        // Referrer-Policy
        if ($this->config['referrer_policy']) {
            $response->headers->set('Referrer-Policy', $this->config['referrer_policy']);
        }
        
        // Permissions-Policy
        if ($this->config['permissions_policy']) {
            $response->headers->set('Permissions-Policy', $this->config['permissions_policy']);
        }
        
        return $response;
    }
    
    public static function create(array $config = []): self
    {
        return new self($config);
    }
    
    public static function production(): self
    {
        return new self([
            'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'hsts' => [
                'max_age' => 63072000, // 2 years
                'include_subdomains' => true,
                'preload' => true
            ]
        ]);
    }
}