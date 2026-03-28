<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Config;

use Core\Attributes\Config\Env;
use Core\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConfigLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset env to a known baseline before each test
        $_ENV['CL_STR']   = 'hello';
        $_ENV['CL_INT']   = '42';
        $_ENV['CL_BOOL']  = 'true';
        $_ENV['CL_FLOAT'] = '3.14';
    }

    // -------------------------------------------------------------------------
    // Basic wiring
    // -------------------------------------------------------------------------

    public function test_loads_string_from_env(): void
    {
        $obj = ConfigLoader::load(StubStringConfig::class);
        $this->assertSame('hello', $obj->value);
    }

    public function test_loads_int_cast_from_env(): void
    {
        $obj = ConfigLoader::load(StubIntConfig::class);
        $this->assertSame(42, $obj->port);
    }

    public function test_loads_bool_cast_from_env(): void
    {
        $obj = ConfigLoader::load(StubBoolConfig::class);
        $this->assertTrue($obj->debug);
    }

    public function test_bool_false_string_cast(): void
    {
        $_ENV['CL_BOOL'] = 'false';
        $obj = ConfigLoader::load(StubBoolConfig::class);
        $this->assertFalse($obj->debug);
    }

    public function test_loads_float_cast_from_env(): void
    {
        $obj = ConfigLoader::load(StubFloatConfig::class);
        $this->assertSame(3.14, $obj->ratio);
    }

    // -------------------------------------------------------------------------
    // Defaults
    // -------------------------------------------------------------------------

    public function test_uses_default_when_env_var_absent(): void
    {
        unset($_ENV['CL_MISSING']);
        $obj = ConfigLoader::load(StubWithDefaultConfig::class);
        $this->assertSame('fallback', $obj->name);
    }

    public function test_env_value_overrides_default(): void
    {
        $_ENV['CL_MISSING'] = 'override';
        $obj = ConfigLoader::load(StubWithDefaultConfig::class);
        $this->assertSame('override', $obj->name);
    }

    public function test_php_default_value_used_when_no_env_attr(): void
    {
        $obj = ConfigLoader::load(StubPhpDefaultConfig::class);
        $this->assertSame('php-default', $obj->tag);
    }

    // -------------------------------------------------------------------------
    // Nullable
    // -------------------------------------------------------------------------

    public function test_nullable_resolves_to_null_when_absent(): void
    {
        unset($_ENV['CL_OPT']);
        $obj = ConfigLoader::load(StubNullableConfig::class);
        $this->assertNull($obj->optional);
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    public function test_throws_when_required_param_has_no_env_and_no_default(): void
    {
        unset($_ENV['CL_STR']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ConfigLoader/');
        ConfigLoader::load(StubRequiredConfig::class);
    }

    // -------------------------------------------------------------------------
    // No-constructor class
    // -------------------------------------------------------------------------

    public function test_no_constructor_class_instantiates_directly(): void
    {
        $obj = ConfigLoader::load(StubNoConstructorConfig::class);
        $this->assertInstanceOf(StubNoConstructorConfig::class, $obj);
    }
}

// ---------------------------------------------------------------------------
// Inline stubs
// ---------------------------------------------------------------------------

#[Config]
final readonly class StubStringConfig
{
    public function __construct(
        #[Env('CL_STR')]
        public string $value,
    ) {}
}

#[Config]
final readonly class StubIntConfig
{
    public function __construct(
        #[Env('CL_INT')]
        public int $port,
    ) {}
}

#[Config]
final readonly class StubBoolConfig
{
    public function __construct(
        #[Env('CL_BOOL')]
        public bool $debug,
    ) {}
}

#[Config]
final readonly class StubFloatConfig
{
    public function __construct(
        #[Env('CL_FLOAT')]
        public float $ratio,
    ) {}
}

#[Config]
final readonly class StubWithDefaultConfig
{
    public function __construct(
        #[Env('CL_MISSING', default: 'fallback')]
        public string $name,
    ) {}
}

#[Config]
final readonly class StubPhpDefaultConfig
{
    public function __construct(
        public string $tag = 'php-default',
    ) {}
}

#[Config]
final readonly class StubNullableConfig
{
    public function __construct(
        #[Env('CL_OPT')]
        public ?string $optional,
    ) {}
}

#[Config]
final readonly class StubRequiredConfig
{
    public function __construct(
        #[Env('CL_STR')]
        public string $required,
    ) {}
}

#[Config]
class StubNoConstructorConfig {}
