<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Data;

use PHPUnit\Framework\TestCase;
use Tests\Stubs\DTOs\SimpleDTOStub;

class DataTransferObjectTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fill() / from()
    // -------------------------------------------------------------------------

    public function test_from_populates_properties_from_array(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => 30]);

        $this->assertSame('Bijaya', $dto->name);
        $this->assertSame(30, $dto->age);
    }

    public function test_from_ignores_unknown_keys(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'unknown_field' => 'ignored']);

        $this->assertSame('Bijaya', $dto->name);
        $this->assertFalse(isset($dto->unknown_field));
    }

    public function test_optional_properties_default_to_null(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya']);

        $this->assertNull($dto->age);
        $this->assertNull($dto->bio);
        $this->assertNull($dto->tags);
    }

    public function test_array_property_is_set_correctly(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'tags' => ['php', 'api']]);

        $this->assertSame(['php', 'api'], $dto->tags);
    }

    // -------------------------------------------------------------------------
    // toArray()
    // -------------------------------------------------------------------------

    public function test_to_array_returns_all_initialized_properties(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => 30]);
        $array = $dto->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('age', $array);
        $this->assertSame('Bijaya', $array['name']);
        $this->assertSame(30, $array['age']);
    }

    public function test_to_array_includes_null_values_for_set_properties(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => null]);
        $array = $dto->toArray();

        $this->assertArrayHasKey('age', $array);
        $this->assertNull($array['age']);
    }

    // -------------------------------------------------------------------------
    // toJson()
    // -------------------------------------------------------------------------

    public function test_to_json_returns_valid_json_string(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => 30]);
        $json = $dto->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('Bijaya', $decoded['name']);
        $this->assertSame(30, $decoded['age']);
    }

    // -------------------------------------------------------------------------
    // only() / except()
    // -------------------------------------------------------------------------

    public function test_only_returns_subset_of_keys(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => 30, 'bio' => 'dev']);
        $result = $dto->only(['name', 'age']);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertArrayNotHasKey('bio', $result);
    }

    public function test_except_excludes_specified_keys(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => 30, 'bio' => 'dev']);
        $result = $dto->except(['bio']);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertArrayNotHasKey('bio', $result);
    }

    // -------------------------------------------------------------------------
    // has() / get()
    // -------------------------------------------------------------------------

    public function test_has_returns_true_for_existing_property(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya']);

        $this->assertTrue($dto->has('name'));
        $this->assertTrue($dto->has('age')); // exists as property even if null
    }

    public function test_has_returns_false_for_nonexistent_property(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya']);

        $this->assertFalse($dto->has('nonexistent'));
    }

    public function test_get_returns_property_value(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => 25]);

        $this->assertSame('Bijaya', $dto->get('name'));
        $this->assertSame(25, $dto->get('age'));
    }

    public function test_get_returns_default_for_missing_property(): void
    {
        $dto = SimpleDTOStub::from(['name' => 'Bijaya']);

        $this->assertSame('fallback', $dto->get('nonexistent', 'fallback'));
    }

    // -------------------------------------------------------------------------
    // fromRequest()
    // -------------------------------------------------------------------------

    public function test_from_request_fills_from_request_all(): void
    {
        $mockRequest = new class {
            public function all(): array
            {
                return ['name' => 'Bijaya', 'age' => 30];
            }
        };

        $dto = SimpleDTOStub::fromRequest($mockRequest);

        $this->assertSame('Bijaya', $dto->name);
        $this->assertSame(30, $dto->age);
    }

    public function test_from_request_accepts_plain_array(): void
    {
        $dto = SimpleDTOStub::fromRequest(['name' => 'Bijaya', 'age' => 25]);

        $this->assertSame('Bijaya', $dto->name);
    }

    public function test_from_request_throws_for_invalid_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SimpleDTOStub::fromRequest('not a valid input');
    }

    // -------------------------------------------------------------------------
    // fromModel()
    // -------------------------------------------------------------------------

    public function test_from_model_fills_from_model_to_array(): void
    {
        $mockModel = new class {
            public function toArray(): array
            {
                return ['name' => 'FromModel', 'age' => 22];
            }
        };

        $dto = SimpleDTOStub::fromModel($mockModel);

        $this->assertSame('FromModel', $dto->name);
        $this->assertSame(22, $dto->age);
    }

    public function test_from_model_accepts_plain_array(): void
    {
        $dto = SimpleDTOStub::fromModel(['name' => 'Bijaya']);

        $this->assertSame('Bijaya', $dto->name);
    }

    public function test_from_model_throws_for_invalid_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SimpleDTOStub::fromModel('not valid');
    }
}
