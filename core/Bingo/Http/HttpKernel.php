<?php

declare(strict_types=1);

namespace Bingo\Http;

use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Contracts\HttpResponse;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Router\Router;

final readonly class HttpKernel
{
    /**
     * @param \Closure(): void $bootResolver
     * @param \Closure(): ExceptionHandlerInterface $exceptionHandlerResolver
     */
    public function __construct(
        private MiddlewarePipeline $pipeline,
        private Router             $router,
        private \Closure           $bootResolver,
        private \Closure           $exceptionHandlerResolver,
        private ResponseNormalizer $responseNormalizer,
    ) {}

    public function handle(Request $request): HttpResponse
    {
        try {
            ($this->bootResolver)();
            return $this->pipeline->process($request, $this->dispatchRequest(...));
        } catch (\Throwable $e) {
            return ($this->exceptionHandlerResolver)()->handle($e);
        }
    }

    private function dispatchRequest(Request $request): HttpResponse
    {
        return $this->responseNormalizer->normalize($this->router->dispatch($request));
    }
}
