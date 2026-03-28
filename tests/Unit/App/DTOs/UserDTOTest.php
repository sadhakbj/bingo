<?php

declare(strict_types=1);

namespace Tests\Unit\App\DTOs;

use App\DTOs\User\UserDTO;
use PHPUnit\Framework\TestCase;

class UserDTOTest extends TestCase
{
    private function makeDTO(array $overrides = []): UserDTO
    {
        $defaults = [
            'id'         => 1,
            'email'      => 'bijaya@example.com',
            'name'       => 'Bijaya Kuikel',
            'age'        => 25,
            'bio'        => 'Framework author.',
            'created_at' => '2024-01-01T00:00:00+00:00',
            'updated_at' => '2024-01-01T00:00:00+00:00',
            'posts'      => [],
        ];

        $data = array_merge($defaults, $overrides);

        return new UserDTO(
            id:         $data['id'],
            email:      $data['email'],
            name:       $data['name'],
            age:        $data['age'],
            bio:        $data['bio'],
            created_at: $data['created_at'],
            updated_at: $data['updated_at'],
            posts:      $data['posts'],
        );
    }

    // -------------------------------------------------------------------------
    // isAdult()
    // -------------------------------------------------------------------------

    public function test_is_adult_returns_true_for_age_18_and_above(): void
    {
        $this->assertTrue($this->makeDTO(['age' => 18])->isAdult());
        $this->assertTrue($this->makeDTO(['age' => 30])->isAdult());
    }

    public function test_is_adult_returns_false_for_age_below_18(): void
    {
        $this->assertFalse($this->makeDTO(['age' => 17])->isAdult());
    }

    public function test_is_adult_returns_false_when_age_is_null(): void
    {
        $this->assertFalse($this->makeDTO(['age' => null])->isAdult());
    }

    // -------------------------------------------------------------------------
    // getDisplayName()
    // -------------------------------------------------------------------------

    public function test_get_display_name_returns_name(): void
    {
        $this->assertSame('Bijaya Kuikel', $this->makeDTO(['name' => 'Bijaya Kuikel'])->getDisplayName());
    }

    // -------------------------------------------------------------------------
    // getMetadata()
    // -------------------------------------------------------------------------

    public function test_get_metadata_contains_expected_keys(): void
    {
        $metadata = $this->makeDTO()->getMetadata();

        $this->assertArrayHasKey('is_adult', $metadata);
        $this->assertArrayHasKey('post_count', $metadata);
        $this->assertArrayHasKey('profile_complete', $metadata);
    }

    public function test_get_metadata_post_count_matches_posts_array(): void
    {
        $dto = $this->makeDTO(['posts' => [['id' => 1], ['id' => 2]]]);

        $this->assertSame(2, $dto->getMetadata()['post_count']);
    }

    public function test_get_metadata_profile_complete_when_all_fields_set(): void
    {
        $this->assertTrue($this->makeDTO()->getMetadata()['profile_complete']);
    }

    public function test_get_metadata_profile_incomplete_when_bio_missing(): void
    {
        $this->assertFalse($this->makeDTO(['bio' => null])->getMetadata()['profile_complete']);
    }

    public function test_get_metadata_profile_incomplete_when_age_missing(): void
    {
        $this->assertFalse($this->makeDTO(['age' => null])->getMetadata()['profile_complete']);
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function test_to_array_contains_all_public_properties(): void
    {
        $array = $this->makeDTO()->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('age', $array);
        $this->assertArrayHasKey('posts', $array);
    }
}
