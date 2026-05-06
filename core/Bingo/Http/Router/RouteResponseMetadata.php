<?php

declare(strict_types = 1);

namespace Bingo\Http\Router;

use Bingo\Attributes\Route\Header;
use Bingo\Attributes\Route\HttpCode;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies #[HttpCode] and #[Header] from controller + method reflection after the action returns.
 */
final class RouteResponseMetadata
{
    private const DEFAULT_STATUS = 200;

    public static function apply(ReflectionMethod $method, Response $response): void
    {
        $class = $method->getDeclaringClass();

        self::applyHttpCode($method, $class, $response);
        self::applyHeaders($method, $class, $response);
    }

    private static function applyHttpCode(ReflectionMethod $method, ReflectionClass $class, Response $response): void
    {
        if ($response->getStatusCode() !== self::DEFAULT_STATUS) {
            return;
        }

        $code = self::resolveHttpCode($method, $class);
        if ($code !== null) {
            $response->setStatusCode($code);
        }
    }

    private static function resolveHttpCode(ReflectionMethod $method, ReflectionClass $class): ?int
    {
        foreach ($method->getAttributes(HttpCode::class) as $attr) {
            return $attr->newInstance()->code;
        }
        foreach ($class->getAttributes(HttpCode::class) as $attr) {
            return $attr->newInstance()->code;
        }

        return null;
    }

    private static function applyHeaders(ReflectionMethod $method, ReflectionClass $class, Response $response): void
    {
        $merged = [];

        foreach ($class->getAttributes(Header::class) as $attr) {
            $h                = $attr->newInstance();
            $merged[$h->name] = $h->value;
        }
        foreach ($method->getAttributes(Header::class) as $attr) {
            $h                = $attr->newInstance();
            $merged[$h->name] = $h->value;
        }

        foreach ($merged as $name => $value) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
    }
}
