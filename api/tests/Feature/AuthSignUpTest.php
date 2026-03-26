<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthSignUpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user sign-up functionality.
     *
     * @return void
     */
    public function test_user_can_sign_up()
    {
        // Arrange: Prepare the data for sign-up
        $name = 'Test User';

        // Act: Call the sign-up endpoint
        $response = $this->postJson('/signup', [
            'name' => $name,
        ]);

        // Assert: Check the response and database
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'uuid',
                'name',
                'level',
                'rank',
                'last_login_at',
            ]
        ]);

        $this->assertDatabaseHas('trx_account', [
            'name' => $name,
        ]);

        $responseData = $response->json('data');
        $this->assertTrue(Str::isUuid($responseData['uuid']));
    }
}
