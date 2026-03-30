<?php

declare(strict_types=1);

namespace Bingo\Http\Middleware;

use Bingo\Contracts\MiddlewareInterface;
use Bingo\Http\Request;
use Bingo\Http\Response;

class CompressionMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'level' => 6, // Compression level 1-9
            'threshold' => 1024, // Minimum bytes to compress
            'types' => [
                'application/json',
                'application/xml',
                'text/html',
                'text/plain',
                'text/css',
                'text/javascript',
                'application/javascript'
            ]
        ], $config);
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next ? $next($request) : Response::json(['message' => 'OK']);

        // Only compress if client accepts gzip
        $acceptEncoding = $request->headers->get('Accept-Encoding', '');
        if (strpos($acceptEncoding, 'gzip') === false) {
            return $response;
        }

        $content = $response->getContent();
        $contentType = $response->headers->get('Content-Type', '');

        // Check if content should be compressed
        if (!$this->shouldCompress($content, $contentType)) {
            return $response;
        }

        // Compress the content
        $compressed = gzencode($content, $this->config['level']);

        if ($compressed !== false && strlen($compressed) < strlen($content)) {
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Content-Length', (string) strlen($compressed));
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }

    private function shouldCompress(string $content, string $contentType): bool
    {
        // Don't compress small content
        if (strlen($content) < $this->config['threshold']) {
            return false;
        }

        // Check if content type should be compressed
        foreach ($this->config['types'] as $type) {
            if (strpos($contentType, $type) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }
}
