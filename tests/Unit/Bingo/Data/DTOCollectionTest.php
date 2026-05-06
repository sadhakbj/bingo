<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Data;

use Bingo\Data\DTOCollection;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\DTOs\SimpleDTOStub;

class DTOCollectionTest extends TestCase
{
    private function makeCollection(int $count = 3): DTOCollection
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = ['name' => "User $i", 'age' => $i * 10];
        }

        return DTOCollection::make($items, SimpleDTOStub::class);
    }

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function test_make_creates_collection_from_array_of_arrays(): void
    {
        $collection = $this->makeCollection(3);

        $this->assertCount(3, $collection);
    }

    public function test_make_hydrates_dto_instances(): void
    {
        $collection = $this->makeCollection(1);

        $this->assertInstanceOf(SimpleDTOStub::class, $collection->first());
    }

    public function test_collection_accepts_dto_instances_directly(): void
    {
        $dto        = SimpleDTOStub::from(['name' => 'Bijaya', 'age' => 30]);
        $collection = new DTOCollection([$dto]);

        $this->assertCount(1, $collection);
        $this->assertSame($dto, $collection->first());
    }

    public function test_empty_collection_has_zero_count(): void
    {
        $collection = new DTOCollection();

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    // -------------------------------------------------------------------------
    // add()
    // -------------------------------------------------------------------------

    public function test_add_appends_dto_to_collection(): void
    {
        $collection = new DTOCollection();
        $dto        = SimpleDTOStub::from(['name' => 'Bijaya']);
        $collection->add($dto);

        $this->assertCount(1, $collection);
    }

    // -------------------------------------------------------------------------
    // first() / last()
    // -------------------------------------------------------------------------

    public function test_first_returns_first_dto(): void
    {
        $collection = $this->makeCollection(3);
        $first      = $collection->first();

        $this->assertInstanceOf(SimpleDTOStub::class, $first);
        $this->assertSame('User 1', $first->name);
    }

    public function test_last_returns_last_dto(): void
    {
        $collection = $this->makeCollection(3);
        $last       = $collection->last();

        $this->assertInstanceOf(SimpleDTOStub::class, $last);
        $this->assertSame('User 3', $last->name);
    }

    public function test_first_returns_null_on_empty_collection(): void
    {
        $collection = new DTOCollection();

        $this->assertNull($collection->first());
    }

    // -------------------------------------------------------------------------
    // filter()
    // -------------------------------------------------------------------------

    public function test_filter_returns_matching_items(): void
    {
        $collection = $this->makeCollection(3); // ages: 10, 20, 30

        $filtered = $collection->filter(fn(SimpleDTOStub $dto) => $dto->age >= 20);

        $this->assertCount(2, $filtered);
    }

    public function test_filter_returns_new_collection_instance(): void
    {
        $collection = $this->makeCollection(3);
        $filtered   = $collection->filter(fn() => true);

        $this->assertNotSame($collection, $filtered);
        $this->assertInstanceOf(DTOCollection::class, $filtered);
    }

    public function test_filter_with_no_matches_returns_empty_collection(): void
    {
        $collection = $this->makeCollection(3);
        $filtered   = $collection->filter(fn() => false);

        $this->assertTrue($filtered->isEmpty());
    }

    // -------------------------------------------------------------------------
    // map()
    // -------------------------------------------------------------------------

    public function test_map_transforms_items_to_array(): void
    {
        $collection = $this->makeCollection(3);
        $names      = $collection->map(fn(SimpleDTOStub $dto) => $dto->name);

        $this->assertSame(['User 1', 'User 2', 'User 3'], $names);
    }

    // -------------------------------------------------------------------------
    // toArray() / toJson()
    // -------------------------------------------------------------------------

    public function test_to_array_converts_all_dtos(): void
    {
        $collection = $this->makeCollection(2);
        $array      = $collection->toArray();

        $this->assertIsArray($array);
        $this->assertCount(2, $array);
        $this->assertArrayHasKey('name', $array[0]);
    }

    public function test_to_json_is_valid_json(): void
    {
        $collection = $this->makeCollection(2);
        $json       = $collection->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertCount(2, $decoded);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function test_array_access_offset_exists(): void
    {
        $collection = $this->makeCollection(2);

        $this->assertTrue(isset($collection[0]));
        $this->assertTrue(isset($collection[1]));
        $this->assertFalse(isset($collection[99]));
    }

    public function test_array_access_offset_get(): void
    {
        $collection = $this->makeCollection(2);

        $this->assertInstanceOf(SimpleDTOStub::class, $collection[0]);
        $this->assertSame('User 1', $collection[0]->name);
    }

    public function test_array_access_offset_set(): void
    {
        $collection   = new DTOCollection();
        $dto          = SimpleDTOStub::from(['name' => 'New']);
        $collection[] = $dto;

        $this->assertCount(1, $collection);
    }

    // -------------------------------------------------------------------------
    // Iterator
    // -------------------------------------------------------------------------

    public function test_foreach_iterates_all_items(): void
    {
        $collection = $this->makeCollection(3);
        $names      = [];

        foreach ($collection as $dto) {
            $names[] = $dto->name;
        }

        $this->assertSame(['User 1', 'User 2', 'User 3'], $names);
    }

    public function test_collection_can_be_iterated_multiple_times(): void
    {
        $collection = $this->makeCollection(2);

        $first = [];
        foreach ($collection as $dto) {
            $first[] = $dto->name;
        }

        $second = [];
        foreach ($collection as $dto) {
            $second[] = $dto->name;
        }

        $this->assertSame($first, $second);
    }
}
