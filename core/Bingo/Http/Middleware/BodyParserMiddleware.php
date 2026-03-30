<?php

declare(strict_types=1);

namespace Bingo\Http\Middleware;

use Bingo\Contracts\HttpResponse;
use Bingo\Contracts\MiddlewareInterface;
use Bingo\Exceptions\Http\BadRequestException;
use Bingo\Http\Request;
use JsonException;

class BodyParserMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'json' => [
                'limit' => '10mb',
                'strict' => true,
                'type' => 'application/json',
                'verify' => null,
            ],
            'urlencoded' => [
                'limit' => '10mb',
                'extended' => true,
                'type' => 'application/x-www-form-urlencoded',
            ],
            'raw' => [
                'limit' => '10mb',
                'type' => 'application/octet-stream',
            ],
            'text' => [
                'limit' => '10mb',
                'type' => 'text/plain',
            ],
        ], $config);
    }

    public function handle(Request $request, callable $next): HttpResponse
    {
        try {
            $this->parseBody($request);
        } catch (\Exception $e) {
            $detail = $e->getMessage() !== '' ? ': ' . $e->getMessage() : '';
            throw new BadRequestException('Body parsing failed' . $detail, $e);
        }

        return $next($request);
    }

    /**
     * @throws \Exception
     */
    private function parseBody(Request $request): void
    {
        $contentType   = $request->headers->get('Content-Type', '') ?? '';
        $contentLength = (int) ($request->headers->get('Content-Length') ?? 0);

        // Check content length limits
        $this->validateContentLength($contentLength);

        // Get raw body content
        $body = $request->getContent();

        if (empty($body)) {
            return; // No body to parse
        }

        // Parse based on content type
        if ($this->isJsonRequest($contentType)) {
            $this->parseJsonBody($request, $body);
        } elseif ($this->isUrlEncodedRequest($contentType)) {
            $this->parseUrlEncodedBody($request, $body);
        } elseif ($this->isTextRequest($contentType)) {
            $this->parseTextBody($request, $body);
        } elseif ($this->isMultipartRequest($contentType)) {
            // Multipart data is already parsed by PHP into $_POST and $_FILES
            $this->parseMultipartBody($request);
        } else {
            // Store as raw body for other content types
            $this->parseRawBody($request, $body);
        }
    }

    private function validateContentLength(int $contentLength): void
    {
        $maxSize = $this->parseSize($this->config['json']['limit']);

        if ($contentLength > $maxSize) {
            throw new \RuntimeException("Request body too large. Maximum size is {$this->config['json']['limit']}");
        }
    }

    private function parseJsonBody(Request $request, string $body): void
    {
        if (empty($body)) {
            return;
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            if ($this->config['json']['strict'] && !is_array($data)) {
                throw new JsonException('JSON body must be an object or array');
            }

            // Store parsed JSON data in request
            $request->attributes->set('_parsed_body', $data);
            $request->attributes->set('_body_type', 'json');

            // Also merge into request data for compatibility
            if (is_array($data)) {
                $request->request->replace($data);
            }

        } catch (JsonException $e) {
            throw new \Exception('Invalid JSON: ' . $e->getMessage());
        }
    }

    private function parseUrlEncodedBody(Request $request, string $body): void
    {
        parse_str($body, $data);

        $request->attributes->set('_parsed_body', $data);
        $request->attributes->set('_body_type', 'urlencoded');

        // Merge into request data
        $request->request->replace($data);
    }

    private function parseTextBody(Request $request, string $body): void
    {
        $request->attributes->set('_parsed_body', $body);
        $request->attributes->set('_body_type', 'text');
    }

    private function parseRawBody(Request $request, string $body): void
    {
        $request->attributes->set('_parsed_body', $body);
        $request->attributes->set('_body_type', 'raw');
    }

    private function parseMultipartBody(Request $request): void
    {
        // PHP automatically parses multipart data into $_POST and $_FILES
        $request->attributes->set('_parsed_body', $request->request->all());
        $request->attributes->set('_body_type', 'multipart');
    }

    private function isJsonRequest(string $contentType): bool
    {
        $jsonTypes = [
            'application/json',
            'application/ld+json',
            'application/vnd.api+json'
        ];

        foreach ($jsonTypes as $type) {
            if (stripos($contentType, $type) === 0) {
                return true;
            }
        }

        return false;
    }

    private function isUrlEncodedRequest(string $contentType): bool
    {
        return stripos($contentType, 'application/x-www-form-urlencoded') === 0;
    }

    private function isTextRequest(string $contentType): bool
    {
        return stripos($contentType, 'text/plain') === 0;
    }

    private function isMultipartRequest(string $contentType): bool
    {
        return stripos($contentType, 'multipart/form-data') === 0;
    }

    private function parseSize(string $size): int
    {
        $units = ['b' => 1, 'kb' => 1024, 'mb' => 1048576, 'gb' => 1073741824];
        $size = strtolower(trim($size));

        if (is_numeric($size)) {
            return (int) $size;
        }

        preg_match('/^(\d+(?:\.\d+)?)\s*([a-z]*)?$/', $size, $matches);

        if (!$matches) {
            throw new \InvalidArgumentException("Invalid size format: {$size}");
        }

        $value = (float) $matches[1];
        $unit = $matches[2] ?? 'b';

        if (!isset($units[$unit])) {
            throw new \InvalidArgumentException("Unknown size unit: {$unit}");
        }

        return (int) ($value * $units[$unit]);
    }

    /**
     * Create body parser middleware with Express.js style configuration
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }

    /**
     * JSON body parser only (like express.json())
     */
    public static function json(array $options = []): self
    {
        return new self([
            'json' => array_merge([
                'limit' => '10mb',
                'strict' => true,
            ], $options)
        ]);
    }

    /**
     * URL-encoded body parser only (like express.urlencoded())
     */
    public static function urlencoded(array $options = []): self
    {
        return new self([
            'urlencoded' => array_merge([
                'limit' => '10mb',
                'extended' => true,
            ], $options)
        ]);
    }

    /**
     * Production-ready body parser with smaller limits
     */
    public static function production(): self
    {
        return new self([
            'json' => ['limit' => '1mb'],
            'urlencoded' => ['limit' => '1mb'],
            'raw' => ['limit' => '1mb'],
            'text' => ['limit' => '100kb'],
        ]);
    }
}
