<?php

declare(strict_types=1);

namespace Bingo\Http;

use Bingo\Contracts\HttpResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

final class ResponseNormalizer
{
    public function normalize(mixed $response): HttpResponse
    {
        if ($response instanceof HttpResponse) {
            return $response;
        }

        if ($response instanceof SymfonyStreamedResponse) {
            return new StreamedResponse(
                $response->getCallback(),
                $response->getStatusCode(),
                $response->headers->all(),
            );
        }

        if ($response instanceof SymfonyResponse) {
            $content = $response->getContent();
            $wrapped = new Response($content === false ? '' : $content, $response->getStatusCode());
            $wrapped->headers->replace($response->headers->all());

            return $wrapped;
        }

        return new Response((string) $response);
    }
}
