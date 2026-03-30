<?php

declare(strict_types=1);

namespace Bingo\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response extends SymfonyResponse
{
    public static function json($data, int $status = 200, array $headers = [], int $options = 0): self
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
        ], $headers);
        $json = json_encode($data, $options | JSON_UNESCAPED_UNICODE);
        return new self($json, $status, $headers);
    }
}
