<?php

declare(strict_types = 1);

namespace Bingo\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends SymfonyRequest
{
    public function all(): array
    {
        $data = array_merge($this->query->all(), $this->request->all());

        // Handle JSON requests
        $contentType = $this->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $content = $this->getContent();
            if ($content) {
                $jsonData = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $data = array_merge($data, $jsonData);
                }
            }
        }

        return $data;
    }

    public function input(string $key, $default = null)
    {
        return $this->request->get($key, $this->query->get($key, $default));
    }

    public function only(array $keys): array
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $data = $this->all();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return $data;
    }
}
