<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use Core\Container\Container;
use Core\Container\ContainerException;
use Core\Container\NotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\Services\StubCircularA;
use Tests\Stubs\Services\StubCircularB;
use Tests\Stubs\Services\StubService;
use Tests\Stubs\Services\StubServiceWithDep;
use Tests\Stubs\Services\StubServiceWithPrimitive;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton(StubService::class);

        $a = $this->container->get(StubService::class);
        $b = $this->container->get(StubService::class);

        $this->assertSame($a, $b);
    }

    public function test_singleton_with_interface_mapping_resolves_concrete(): void
    {
        $this->container->singleton(StubServiceInterface::class, StubService::class);

        $result = $this->container->get(StubServiceInterface::class);

        $this->assertInstanceOf(StubService::class, $result);
    }

    // -------------------------------------------------------------------------
    // Bind (transient)
    // -------------------------------------------------------------------------

    public function test_bind_returns_new_instance_each_time(): void
    {
        $this->container->bind(StubService::class);

        $a = $this->container->get(StubService::class);
        $b = $this->container->get(StubService::class);

        $this->assertNotSame($a, $b);
    }

    public function test_bind_with_concrete_mapping(): void
    {
        $this->container->bind(StubServiceInterface::class, StubService::class);

        $result = $this->container->get(StubServiceInterface::class);

        $this->assertInstanceOf(StubService::class, $result);
    }

    // -------------------------------------------------------------------------
    // Instance (pre-built)
    // -------------------------------------------------------------------------

    public function test_instance_returns_prebuilt_object(): void
    {
        $obj = new StubService();
        $obj->value = 42;

        $this->container->instance(StubService::class, $obj);

        $this->assertSame($obj, $this->container->get(StubService::class));
        $this->assertSame(42, $this->container->get(StubService::class)->value);
    }

    public function test_instance_bypasses_compile_and_is_always_available(): void
    {
        $obj = new StubService();
        $this->container->instance(StubService::class, $obj);
        $this->container->compile();

        $this->assertSame($obj, $this->container->get(StubService::class));
    }

    // -------------------------------------------------------------------------
    // has()
    // -------------------------------------------------------------------------

    public function test_has_true_for_registered_singleton(): void
    {
        $this->container->singleton(StubService::class);

        $this->assertTrue($this->container->has(StubService::class));
    }

    public function test_has_true_for_prebuilt_instance(): void
    {
        $this->container->instance(StubService::class, new StubService());

        $this->assertTrue($this->container->has(StubService::class));
    }

    public function test_has_true_for_unregistered_concrete_class(): void
    {
        // Reflection can always resolve a concrete class — has() should return true
        $this->assertTrue($this->container->has(StubService::class));
    }

    public function test_has_false_for_interface_without_binding(): void
    {
        $this->assertFalse($this->container->has(StubServiceInterface::class));
    }

    // -------------------------------------------------------------------------
    // make() alias
    // -------------------------------------------------------------------------

    public function test_make_is_alias_for_get(): void
    {
        $this->container->singleton(StubService::class);

        $this->assertSame(
            $this->container->get(StubService::class),
            $this->container->make(StubService::class)
        );
    }

    // -------------------------------------------------------------------------
    // Reflection fallback (zero-config autowiring)
    // -------------------------------------------------------------------------

    public function test_reflection_resolves_class_with_no_constructor(): void
    {
        $result = $this->container->get(StubService::class);

        $this->assertInstanceOf(StubService::class, $result);
    }

    public function test_reflection_resolves_typed_constructor_dependency(): void
    {
        $result = $this->container->get(StubServiceWithDep::class);

        $this->assertInstanceOf(StubServiceWithDep::class, $result);
        $this->assertInstanceOf(StubService::class, $result->service);
    }

    public function test_reflection_uses_registered_singleton_for_dependency(): void
    {
        // Register StubService as singleton
        $this->container->singleton(StubService::class);

        $dep1 = $this->container->get(StubServiceWithDep::class);
        $dep2 = $this->container->get(StubServiceWithDep::class);

        // Both resolutions should share the same StubService singleton
        $this->assertSame($dep1->service, $dep2->service);
    }

    public function test_reflection_resolves_optional_params_with_defaults(): void
    {
        $result = $this->container->get(StubServiceWithOptional::class);

        $this->assertSame('default', $result->name);
    }

    public function test_reflection_resolves_nullable_unresolvable_param_as_null(): void
    {
        // StubServiceWithNullable has ?UnresolvableInterface $dep = null.
        // The interface has no binding, so the container falls back to null.
        $result = $this->container->get(StubServiceWithNullable::class);

        $this->assertNull($result->dep);
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function test_compile_is_idempotent(): void
    {
        $this->container->singleton(StubService::class);
        $this->container->compile();
        $this->container->compile(); // second call must not throw

        $this->assertInstanceOf(StubService::class, $this->container->get(StubService::class));
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    public function test_registering_after_compile_throws_ContainerException(): void
    {
        $this->container->compile();

        $this->expectException(ContainerException::class);
        $this->container->singleton(StubService::class);
    }

    public function test_nonexistent_class_throws_NotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('NonExistent\Class\That\Does\Not\Exist');
    }

    public function test_interface_without_binding_throws_NotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get(StubServiceInterface::class);
    }

    public function test_unresolvable_primitive_param_throws_ContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->container->get(StubServiceWithPrimitive::class);
    }

    public function test_circular_dependency_throws_ContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/[Cc]ircular/');
        $this->container->get(StubCircularA::class);
    }
}

// ---------------------------------------------------------------------------
// Inline stubs (interface + optional/nullable classes)
// ---------------------------------------------------------------------------

interface StubServiceInterface {}

interface StubUnresolvableInterface {} // no binding — triggers nullable fallback

class StubServiceWithOptional
{
    public function __construct(
        public readonly string $name = 'default'
    ) {}
}

class StubServiceWithNullable
{
    public function __construct(
        public readonly ?StubUnresolvableInterface $dep = null
    ) {}
}
