<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class AuthSignUpTest extends TestCase
{
    use RefreshMultipleDatabases;

    /**
     * Override to prevent automatic transaction wrapping
     * because UseCases manage their own transactions
     */
    public function beginDatabaseTransaction(): void
    {
        // Do nothing - let the UseCase handle transactions
    }

    /**
     * Test user sign-up functionality.
     *
     * @return void
     */
    public function test_user_can_sign_up()
    {
        // Arrange: Prepare the data for sign-up
        $deviceId = 'test-device-'.Str::uuid();

        // Act: Call the sign-up endpoint
        $response = $this->postJson('/api/signup', [
            'device_id' => $deviceId,
            'device_info' => [
                'os' => 'iOS',
                'os_version' => '17.0',
                'model' => 'iPhone 15 Pro',
                'app_version' => '1.0.0',
            ],
        ]);

        // Assert: Check the response and database
        $response->assertOk(); // 200 OK

        // Check basic response structure
        $this->assertNotNull($response->json());
    }
}
