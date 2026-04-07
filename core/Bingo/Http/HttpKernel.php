<?php

declare(strict_types=1);

namespace Bingo\Http;

use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Contracts\HttpResponse;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Router\Router;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

final readonly class HttpKernel
{
    /**
     * @param \Closure(): ExceptionHandlerInterface $exceptionHandlerResolver
     */
    public function __construct(
        private MiddlewarePipeline $pipeline,
        private Router $router,
        private \Closure $exceptionHandlerResolver,
    ) {}

    public function handle(Request $request): HttpResponse
    {
        try {
            return $this->pipeline->process($request, $this->dispatchRequest(...));
        } catch (\Throwable $e) {
            return ($this->exceptionHandlerResolver)()->handle($e);
        }
    }

    private function dispatchRequest(Request $request): HttpResponse
    {
        return $this->normalizeResponse($this->router->dispatch($request));
    }

    private function normalizeResponse(mixed $response): HttpResponse
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
