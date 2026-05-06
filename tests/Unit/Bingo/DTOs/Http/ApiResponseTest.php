<?php

declare(strict_types = 1);

namespace Tests\Unit\Bingo\DTOs\Http;

use Bingo\DTOs\Http\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // success()
    // -------------------------------------------------------------------------

    public function test_success_sets_correct_defaults(): void
    {
        $response = ApiResponse::success();

        $this->assertTrue($response->success);
        $this->assertSame(200, $response->status_code);
        $this->assertSame('Success', $response->message);
        $this->assertNull($response->data);
        $this->assertNull($response->errors);
    }

    public function test_success_with_data_and_custom_message(): void
    {
        $response = ApiResponse::success(['id' => 1], 'User created', 201);

        $this->assertTrue($response->success);
        $this->assertSame(201, $response->status_code);
        $this->assertSame('User created', $response->message);
        $this->assertSame(['id' => 1], $response->data);
    }

    public function test_success_with_meta(): void
    {
        $response = ApiResponse::success([], 'OK', 200, ['total' => 100, 'page' => 1]);

        $this->assertSame(['total' => 100, 'page' => 1], $response->meta);
    }

    public function test_success_has_no_errors(): void
    {
        $response = ApiResponse::success(['user' => 'data']);

        $this->assertNull($response->errors);
    }

    // -------------------------------------------------------------------------
    // error()
    // -------------------------------------------------------------------------

    public function test_error_sets_success_false(): void
    {
        $response = ApiResponse::error('Something went wrong');

        $this->assertFalse($response->success);
        $this->assertSame('Something went wrong', $response->message);
        $this->assertSame(400, $response->status_code);
    }

    public function test_error_with_custom_status_code(): void
    {
        $response = ApiResponse::error('Server error', null, 500);

        $this->assertSame(500, $response->status_code);
        $this->assertFalse($response->success);
    }

    public function test_error_with_errors_array(): void
    {
        $errors   = ['field' => 'Required'];
        $response = ApiResponse::error('Validation failed', $errors, 422);

        $this->assertSame($errors, $response->errors);
    }

    // -------------------------------------------------------------------------
    // notFound()
    // -------------------------------------------------------------------------

    public function test_not_found_returns_404(): void
    {
        $response = ApiResponse::notFound();

        $this->assertFalse($response->success);
        $this->assertSame(404, $response->status_code);
        $this->assertSame('Resource not found', $response->message);
    }

    public function test_not_found_with_custom_message(): void
    {
        $response = ApiResponse::notFound('User not found');

        $this->assertSame('User not found', $response->message);
        $this->assertSame(404, $response->status_code);
    }

    // -------------------------------------------------------------------------
    // unauthorized()
    // -------------------------------------------------------------------------

    public function test_unauthorized_returns_401(): void
    {
        $response = ApiResponse::unauthorized();

        $this->assertFalse($response->success);
        $this->assertSame(401, $response->status_code);
        $this->assertSame('Unauthorized', $response->message);
    }

    // -------------------------------------------------------------------------
    // forbidden()
    // -------------------------------------------------------------------------

    public function test_forbidden_returns_403(): void
    {
        $response = ApiResponse::forbidden();

        $this->assertFalse($response->success);
        $this->assertSame(403, $response->status_code);
        $this->assertSame('Forbidden', $response->message);
    }

    // -------------------------------------------------------------------------
    // validation()
    // -------------------------------------------------------------------------

    public function test_validation_returns_422_with_errors(): void
    {
        $errors   = ['email' => 'Email is required', 'name' => 'Name is too short'];
        $response = ApiResponse::validation($errors);

        $this->assertFalse($response->success);
        $this->assertSame(422, $response->status_code);
        $this->assertSame('Validation failed', $response->message);
        $this->assertSame($errors, $response->errors);
    }

    public function test_validation_with_custom_message(): void
    {
        $response = ApiResponse::validation(['field' => 'error'], 'Invalid input');

        $this->assertSame('Invalid input', $response->message);
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function test_to_array_contains_all_expected_keys(): void
    {
        $response = ApiResponse::success(['user' => 'data']);
        $array    = $response->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('status_code', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function test_to_json_is_valid_json(): void
    {
        $response = ApiResponse::success(['key' => 'value']);
        $json     = $response->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['success']);
    }

    public function test_timestamp_is_set_automatically(): void
    {
        $response = ApiResponse::success();

        $this->assertNotEmpty($response->timestamp);
        // Should be a valid ISO 8601 date
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $response->timestamp);
    }
}
