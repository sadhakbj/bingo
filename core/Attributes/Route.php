<?php

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public string $path;
    public string $method;

    public function __construct(string $path, string $method = 'GET')
    {
        $this->path = $path;
        $this->method = strtoupper($method);
    }
}

// NestJS-style Parameter Attributes

#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    public function __construct(
        public readonly ?string $dtoClass = null
    ) {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class Query
{
    public function __construct(
        public readonly ?string $key = null
    ) {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
    public function __construct(
        public readonly string $key
    ) {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class Headers
{
    public function __construct(
        public readonly ?string $key = null
    ) {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class Request
{
    public function __construct() {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class UploadedFile
{
    public function __construct(
        public readonly ?string $key = null
    ) {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class UploadedFiles
{
    public function __construct() {}
}
