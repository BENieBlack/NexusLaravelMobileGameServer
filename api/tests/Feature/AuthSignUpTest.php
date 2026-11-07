<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
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
        $response = $this->postJson('/api/auth/sign_up', [
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

    /**
     * device_info は任意。省略しても、サインアップできる。
     *
     * SignUpRequest は device_info を nullable にしているが、UseCase は array を受け取る。
     * 以前は null がそのまま渡り、TypeError で HTTP 500（error_code: 999）になっていた。
     */
    public function test_user_can_sign_up_without_device_info()
    {
        $deviceId = 'test-device-'.Str::uuid();

        $response = $this->postJson('/api/auth/sign_up', ['device_id' => $deviceId]);

        $response->assertOk();
        $response->assertJsonStructure(['sys_player' => ['id', 'my_id'], 'token' => ['access_token']]);

        // device_info は空として保存される
        $deviceInfo = DB::connection('sys')->table('sys_player_device')->where('uuid', $deviceId)->value('device_info');
        $this->assertContains($deviceInfo, [null, '[]', '{}'], 'device_info は空で保存される');
    }

    /**
     * device_info に null を明示しても、省略と同じに扱う。
     */
    public function test_user_can_sign_up_with_null_device_info()
    {
        $response = $this->postJson('/api/auth/sign_up', [
            'device_id' => 'test-device-'.Str::uuid(),
            'device_info' => null,
        ]);

        $response->assertOk();
    }
}
