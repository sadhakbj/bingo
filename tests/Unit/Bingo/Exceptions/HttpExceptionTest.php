<?php

declare(strict_types = 1);

namespace Tests\Unit\Bingo\Exceptions;

use Bingo\Exceptions\Http\BadRequestException;
use Bingo\Exceptions\Http\ConflictException;
use Bingo\Exceptions\Http\ForbiddenException;
use Bingo\Exceptions\Http\HttpException;
use Bingo\Exceptions\Http\ImATeapotException;
use Bingo\Exceptions\Http\NotFoundException;
use Bingo\Exceptions\Http\UnauthorizedException;
use Bingo\Http\Response;
use PHPUnit\Framework\TestCase;

class HttpExceptionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // HttpException base
    // -------------------------------------------------------------------------

    public function test_http_exception_stores_status_code(): void
    {
        $e = new HttpException(418, "I'm a teapot");

        $this->assertSame(418, $e->getStatusCode());
        $this->assertSame("I'm a teapot", $e->getMessage());
    }

    public function test_http_exception_accepts_symfony_response_constants(): void
    {
        $e = new HttpException(Response::HTTP_FORBIDDEN, 'Nope');

        $this->assertSame(403, $e->getStatusCode());
        $this->assertSame('Nope', $e->getMessage());
    }

    public function test_http_exception_uses_default_message_when_empty(): void
    {
        $e = new HttpException(404);

        $this->assertSame('Not Found', $e->getMessage());
        $this->assertSame(404, $e->getStatusCode());
    }

    public function test_http_exception_default_messages_for_common_codes(): void
    {
        $cases = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            500 => 'Internal Server Error',
        ];

        foreach ($cases as $code => $expectedMessage) {
            $e = new HttpException($code);
            $this->assertSame($expectedMessage, $e->getMessage(), "Wrong default for $code");
        }
    }

    public function test_http_exception_is_throwable(): void
    {
        $this->expectException(HttpException::class);

        throw new HttpException(500, 'boom');
    }

    // -------------------------------------------------------------------------
    // Concrete subclasses
    // -------------------------------------------------------------------------

    public function test_not_found_exception_is_404(): void
    {
        $e = new NotFoundException();

        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('Not Found', $e->getMessage());
    }

    public function test_not_found_exception_accepts_custom_message(): void
    {
        $e = new NotFoundException('User not found');

        $this->assertSame('User not found', $e->getMessage());
        $this->assertSame(404, $e->getStatusCode());
    }

    public function test_unauthorized_exception_is_401(): void
    {
        $e = new UnauthorizedException();

        $this->assertSame(401, $e->getStatusCode());
        $this->assertSame('Unauthorized', $e->getMessage());
    }

    public function test_forbidden_exception_is_403(): void
    {
        $e = new ForbiddenException();

        $this->assertSame(403, $e->getStatusCode());
        $this->assertSame('Forbidden', $e->getMessage());
    }

    public function test_bad_request_exception_is_400(): void
    {
        $e = new BadRequestException();

        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('Bad Request', $e->getMessage());
    }

    public function test_conflict_exception_is_409(): void
    {
        $e = new ConflictException();

        $this->assertSame(409, $e->getStatusCode());
        $this->assertSame('Conflict', $e->getMessage());
    }

    public function test_all_subclasses_extend_http_exception(): void
    {
        $this->assertInstanceOf(HttpException::class, new NotFoundException());
        $this->assertInstanceOf(HttpException::class, new UnauthorizedException());
        $this->assertInstanceOf(HttpException::class, new ForbiddenException());
        $this->assertInstanceOf(HttpException::class, new BadRequestException());
        $this->assertInstanceOf(HttpException::class, new ConflictException());
    }

    public function test_im_a_teapot_exception_default_message(): void
    {
        $e = new ImATeapotException();

        $this->assertSame(418, $e->getStatusCode());
        $this->assertStringContainsString('teapot', strtolower($e->getMessage()));
    }
}
