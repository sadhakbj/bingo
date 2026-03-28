<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Exceptions;

use Core\Exceptions\BadRequestException;
use Core\Exceptions\ConflictException;
use Core\Exceptions\ExceptionHandler;
use Core\Exceptions\ForbiddenException;
use Core\Exceptions\HttpException;
use Core\Exceptions\NotFoundException;
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

    // -------------------------------------------------------------------------
    // ValidationException → 422
    // -------------------------------------------------------------------------

    public function test_validation_exception_returns_422(): void
    {
        $e = new ValidationException(['email' => 'Required', 'name' => 'Too short']);
        $response = $this->handler()->handle($e);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_validation_exception_body_has_errors(): void
    {
        $errors = ['email' => 'Required', 'name' => 'Too short'];
        $response = $this->handler()->handle(new ValidationException($errors));
        $body = $this->decode($response);

        $this->assertFalse($body['success']);
        $this->assertSame($errors, $body['errors']);
        $this->assertSame(422, $body['status_code']);
    }

    public function test_validation_exception_message_is_validation_failed(): void
    {
        $response = $this->handler()->handle(new ValidationException(['field' => 'error']));
        $body = $this->decode($response);

        $this->assertSame('Validation failed', $body['message']);
    }

    // -------------------------------------------------------------------------
    // HttpException subclasses
    // -------------------------------------------------------------------------

    public function test_not_found_exception_returns_404(): void
    {
        $response = $this->handler()->handle(new NotFoundException('User not found'));

        $this->assertSame(404, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame('User not found', $body['message']);
        $this->assertFalse($body['success']);
    }

    public function test_unauthorized_exception_returns_401(): void
    {
        $response = $this->handler()->handle(new UnauthorizedException());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_forbidden_exception_returns_403(): void
    {
        $response = $this->handler()->handle(new ForbiddenException());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_bad_request_exception_returns_400(): void
    {
        $response = $this->handler()->handle(new BadRequestException('Invalid input'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid input', $this->decode($response)['message']);
    }

    public function test_conflict_exception_returns_409(): void
    {
        $response = $this->handler()->handle(new ConflictException('Email already taken'));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('Email already taken', $this->decode($response)['message']);
    }

    public function test_generic_http_exception_uses_its_status_code(): void
    {
        $response = $this->handler()->handle(new HttpException(429, 'Slow down'));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('Slow down', $this->decode($response)['message']);
    }

    // -------------------------------------------------------------------------
    // Generic Throwable → 500
    // -------------------------------------------------------------------------

    public function test_generic_exception_returns_500(): void
    {
        $response = $this->handler()->handle(new \RuntimeException('Something broke'));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function test_generic_exception_hides_details_in_production(): void
    {
        $response = $this->handler(debug: false)->handle(new \RuntimeException('Secret internals'));
        $body = $this->decode($response);

        $this->assertSame('Internal Server Error', $body['message']);
        $this->assertNull($body['data'] ?? null);
    }

    public function test_generic_exception_exposes_details_in_debug_mode(): void
    {
        $response = $this->handler(debug: true)->handle(new \RuntimeException('Real message'));
        $body = $this->decode($response);

        $this->assertSame('Real message', $body['message']);
        $this->assertNotNull($body['data']);
        $this->assertArrayHasKey('exception', $body['data']);
        $this->assertArrayHasKey('file', $body['data']);
        $this->assertArrayHasKey('line', $body['data']);
        $this->assertArrayHasKey('trace', $body['data']);
    }

    public function test_debug_mode_includes_exception_class_name(): void
    {
        $response = $this->handler(debug: true)->handle(new \InvalidArgumentException('bad'));
        $body = $this->decode($response);

        $this->assertSame(\InvalidArgumentException::class, $body['data']['exception']);
    }

    // -------------------------------------------------------------------------
    // All responses are valid JSON with consistent envelope
    // -------------------------------------------------------------------------

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
                $response->headers->get('Content-Type'),
                'Response must be JSON for ' . $e::class,
            );
        }
    }

    public function test_all_responses_have_consistent_envelope_keys(): void
    {
        $exceptions = [
            new ValidationException(['f' => 'e']),
            new NotFoundException(),
            new \RuntimeException('boom'),
        ];

        foreach ($exceptions as $e) {
            $body = $this->decode($this->handler()->handle($e));
            $this->assertArrayHasKey('success', $body);
            $this->assertArrayHasKey('message', $body);
            $this->assertArrayHasKey('status_code', $body);
            $this->assertArrayHasKey('timestamp', $body);
        }
    }

    public function test_all_error_responses_have_success_false(): void
    {
        $exceptions = [
            new ValidationException(['f' => 'e']),
            new NotFoundException(),
            new ForbiddenException(),
            new \RuntimeException('boom'),
        ];

        foreach ($exceptions as $e) {
            $body = $this->decode($this->handler()->handle($e));
            $this->assertFalse($body['success'], 'success must be false for ' . $e::class);
        }
    }
}
