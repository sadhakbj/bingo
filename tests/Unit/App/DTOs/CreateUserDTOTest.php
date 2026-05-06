<?php

declare(strict_types = 1);

namespace Tests\Unit\App\DTOs;

use App\DTOs\CreateUserDTO;
use Bingo\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class CreateUserDTOTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email'    => 'bijaya@example.com',
            'name'     => 'Bijaya Kuikel',
            'age'      => 25,
            'bio'      => 'Framework author.',
            'metadata' => ['source' => 'api'],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_valid_payload_creates_dto(): void
    {
        $dto = CreateUserDTO::fromRequest($this->validPayload());

        $this->assertSame('bijaya@example.com', $dto->email);
        $this->assertSame('Bijaya Kuikel', $dto->name);
        $this->assertSame(25, $dto->age);
    }

    public function test_optional_fields_are_omittable_without_exception(): void
    {
        // readonly nullable properties without defaults are left uninitialized when
        // omitted from input — accessing them throws, but toArray() skips them safely.
        $dto = CreateUserDTO::fromRequest([
            'email' => 'bijaya@example.com',
            'name'  => 'Bijaya Kuikel',
        ]);

        $array = $dto->toArray();

        // Required fields are present
        $this->assertSame('bijaya@example.com', $array['email']);
        $this->assertSame('Bijaya Kuikel', $array['name']);

        // Optional fields are absent from the serialized output (not initialized)
        $this->assertArrayNotHasKey('age', $array);
        $this->assertArrayNotHasKey('bio', $array);
        $this->assertArrayNotHasKey('metadata', $array);
    }

    public function test_metadata_array_is_preserved(): void
    {
        $dto = CreateUserDTO::fromRequest($this->validPayload(['metadata' => ['key' => 'value']]));

        $this->assertSame(['key' => 'value'], $dto->metadata);
    }

    // -------------------------------------------------------------------------
    // Email validation
    // -------------------------------------------------------------------------

    public function test_missing_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['email' => '']));
    }

    public function test_invalid_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['email' => 'not-an-email']));
    }

    public function test_validation_exception_contains_email_field(): void
    {
        try {
            CreateUserDTO::fromRequest($this->validPayload(['email' => 'bad']));
            $this->fail('ValidationException not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors);
        }
    }

    // -------------------------------------------------------------------------
    // Name validation
    // -------------------------------------------------------------------------

    public function test_missing_name_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['name' => '']));
    }

    public function test_name_too_short_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['name' => 'A']));
    }

    public function test_name_too_long_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['name' => str_repeat('A', 51)]));
    }

    public function test_validation_exception_contains_name_field(): void
    {
        try {
            CreateUserDTO::fromRequest($this->validPayload(['name' => '']));
            $this->fail('ValidationException not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    // -------------------------------------------------------------------------
    // Age validation
    // -------------------------------------------------------------------------

    public function test_age_below_18_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['age' => 17]));
    }

    public function test_age_above_120_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['age' => 121]));
    }

    public function test_age_at_boundary_18_is_valid(): void
    {
        $dto = CreateUserDTO::fromRequest($this->validPayload(['age' => 18]));

        $this->assertSame(18, $dto->age);
    }

    public function test_age_at_boundary_120_is_valid(): void
    {
        $dto = CreateUserDTO::fromRequest($this->validPayload(['age' => 120]));

        $this->assertSame(120, $dto->age);
    }

    // -------------------------------------------------------------------------
    // Bio validation
    // -------------------------------------------------------------------------

    public function test_bio_exceeding_500_chars_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest($this->validPayload(['bio' => str_repeat('x', 501)]));
    }

    public function test_bio_exactly_500_chars_is_valid(): void
    {
        $dto = CreateUserDTO::fromRequest($this->validPayload(['bio' => str_repeat('x', 500)]));

        $this->assertSame(500, strlen($dto->bio));
    }

    // -------------------------------------------------------------------------
    // DTO is readonly (immutable)
    // -------------------------------------------------------------------------

    public function test_dto_properties_are_readonly(): void
    {
        $dto        = CreateUserDTO::fromRequest($this->validPayload());
        $reflection = new \ReflectionClass($dto);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
