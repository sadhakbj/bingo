<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Exceptions;

use Core\Exceptions\BadRequestException;
use Core\Exceptions\ConflictException;
use Core\Exceptions\ExceptionHandler;
use Core\Exceptions\ForbiddenException;
use Core\Exceptions\HttpException;
use Core\Exceptions\NotFoundException;
use Core\Exceptions\TooManyRequestsException;
use Core\Exceptions\UnauthorizedException;
use Core\Http\Response;
use Core\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ExceptionHandlerTest extends TestCase
{
    private function handler(bool $debug = false): ExceptionHandler
    {
        return new ExceptionHandler($debug);
    }

    private function decode(Response $response): array
    {
        return json_decode($response->getContent(), true);
    }

    public function test_validation_exception_returns_422_nest_shape(): void
    {
        $errors = ['email' => 'Required', 'name' => 'Too short'];
        $response = $this->handler()->handle(new ValidationException($errors));
        $body = $this->decode($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(422, $body['statusCode']);
        $this->assertSame($errors, $body['message']);
        $this->assertSame('Unprocessable Entity', $body['error']);
    }

    public function test_not_found_exception_returns_404_nest_shape(): void
    {
        $response = $this->handler()->handle(new NotFoundException('User not found'));
        $body = $this->decode($response);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(404, $body['statusCode']);
        $this->assertSame('User not found', $body['message']);
        $this->assertSame('Not Found', $body['error']);
    }

    public function test_unauthorized_exception_returns_401(): void
    {
        $response = $this->handler()->handle(new UnauthorizedException());
        $body = $this->decode($response);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(401, $body['statusCode']);
        $this->assertSame('Unauthorized', $body['error']);
    }

    public function test_forbidden_exception_returns_403(): void
    {
        $response = $this->handler()->handle(new ForbiddenException());
        $body = $this->decode($response);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden', $body['error']);
    }

    public function test_bad_request_exception_returns_400(): void
    {
        $response = $this->handler()->handle(new BadRequestException('Invalid input'));
        $body = $this->decode($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid input', $body['message']);
        $this->assertSame('Bad Request', $body['error']);
    }

    public function test_conflict_exception_returns_409(): void
    {
        $response = $this->handler()->handle(new ConflictException('Email already taken'));
        $body = $this->decode($response);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('Email already taken', $body['message']);
        $this->assertSame('Conflict', $body['error']);
    }

    public function test_generic_http_exception_uses_its_status_code(): void
    {
        $response = $this->handler()->handle(new HttpException(418, 'I am a teapot'));
        $body = $this->decode($response);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('I am a teapot', $body['message']);
        $this->assertSame('HTTP Error', $body['error']);
    }

    public function test_too_many_requests_sets_rate_limit_headers_when_metadata_present(): void
    {
        $response = $this->handler()->handle(
            new TooManyRequestsException(
                'Slow down',
                100,
                0,
                1_700_000_000,
            ),
        );

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('100', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertSame((string) 1_700_000_000, $response->headers->get('X-RateLimit-Reset'));
    }

    public function test_generic_exception_returns_500(): void
    {
        $response = $this->handler()->handle(new \RuntimeException('Something broke'));
        $body = $this->decode($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(500, $body['statusCode']);
        $this->assertSame('Internal Server Error', $body['message']);
        $this->assertSame('Internal Server Error', $body['error']);
    }

    public function test_generic_exception_hides_details_in_production(): void
    {
        $response = $this->handler(debug: false)->handle(new \RuntimeException('Secret internals'));
        $body = $this->decode($response);

        $this->assertArrayNotHasKey('details', $body);
    }

    public function test_generic_exception_exposes_details_in_debug_mode(): void
    {
        $response = $this->handler(debug: true)->handle(new \RuntimeException('Real message'));
        $body = $this->decode($response);

        $this->assertSame('Real message', $body['message']);
        $this->assertArrayHasKey('details', $body);
        $this->assertSame(\RuntimeException::class, $body['details']['exception']);
        $this->assertArrayHasKey('file', $body['details']);
        $this->assertArrayHasKey('line', $body['details']);
        $this->assertArrayHasKey('trace', $body['details']);
    }

    public function test_all_responses_return_json_content_type(): void
    {
        $exceptions = [
            new ValidationException(['f' => 'e']),
            new NotFoundException(),
            new UnauthorizedException(),
            new ForbiddenException(),
            new \RuntimeException('boom'),
        ];

        foreach ($exceptions as $e) {
            $response = $this->handler()->handle($e);
            $this->assertStringContainsString(
                'application/json',
                (string) $response->headers->get('Content-Type'),
                'Response must be JSON for ' . $e::class,
            );
        }
    }

    public function test_all_error_responses_have_nest_core_keys(): void
    {
        $cases = [
            new ValidationException(['f' => 'e']),
            new NotFoundException(),
            new \RuntimeException('boom'),
        ];

        foreach ($cases as $e) {
            $body = $this->decode($this->handler()->handle($e));
            $this->assertArrayHasKey('statusCode', $body);
            $this->assertArrayHasKey('message', $body);
            $this->assertArrayHasKey('error', $body);
        }
    }
}
