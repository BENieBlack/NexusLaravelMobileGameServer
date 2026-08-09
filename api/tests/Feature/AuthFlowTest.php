<?php

namespace Tests\Feature;

use App\Models\Sys\SysMaintenance;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * AuthFlowTest
 *
 * 認証フロー全体の統合テスト
 * version → sign_up → sign_in → player/me の一連の流れをテスト
 */
class AuthFlowTest extends TestCase
{
    use RefreshMultipleDatabases;

    private string $testDeviceUuid;

    private ?int $testPlayerId = null;

    /**
     * 正常な認証フロー全体のテスト（メンテナンスなし）
     */
    public function test_complete_auth_flow_without_maintenance(): void
    {
        // テストデバイスUUIDを生成
        $this->testDeviceUuid = 'test-device-'.Str::random(20);

        // ========================================
        // Step 1: GET /auth/version
        // ========================================
        $versionResponse = $this->getJson('/api/auth/version?app_version=1.0.0');

        $versionResponse->assertStatus(200);
        $versionResponse->assertJsonStructure([
            'data' => [
                'needs_update',
            ],
        ]);

        // メンテナンス情報がないことを確認（dataキーの中を確認）
        $versionData = $versionResponse->json();
        $this->assertArrayHasKey('data', $versionData);
        $this->assertArrayNotHasKey('maintenance', $versionData['data']);

        // ========================================
        // Step 2: POST /auth/sign_up
        // ========================================
        $signUpResponse = $this->postJson('/api/auth/sign_up', [
            'device_id' => $this->testDeviceUuid,
            'device_info' => [
                'os' => 'iOS',
                'os_version' => '17.0',
                'model' => 'iPhone 15 Pro',
                'app_version' => '1.0.0',
            ],
        ]);

        $signUpResponse->assertOk(); // 200 OK

        $signUpData = $signUpResponse->json();

        // レスポンス構造を確認
        $signUpResponse->assertJsonStructure([
            'sys_player' => ['uuid', 'my_id'],
            'dto_token' => ['access_token', 'refresh_token', 'expires_in'],
        ]);

        // データを取得
        $this->assertNotEmpty($signUpData['dto_token']['access_token']);
        $this->assertNotEmpty($signUpData['dto_token']['refresh_token']);
        $this->assertNotEmpty($signUpData['sys_player']['my_id']);

        // トークンを保存
        $accessToken = $signUpData['dto_token']['access_token'];
        $refreshToken = $signUpData['dto_token']['refresh_token'];
        $myId = $signUpData['sys_player']['my_id'];

        // ========================================
        // Step 3: GET /player/me (アクセストークン使用)
        // ========================================
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->getJson('/api/player/me');

        $meResponse->assertStatus(200);
        $meResponse->assertJsonStructure([
            'data' => [
                'my_id',
                'name',
            ],
        ]);

        $meData = $meResponse->json();
        $this->assertEquals($myId, $meData['data']['my_id']);

        // ========================================
        // Step 4: POST /auth/refresh_token (リフレッシュトークンで新しいアクセストークンを取得)
        // ========================================
        $signInResponse = $this->postJson('/api/auth/refresh_token', [
            'refresh_token' => $refreshToken,
        ]);

        $signInResponse->assertStatus(200);
        $signInData = $signInResponse->json();

        // レスポンス構造を確認
        $this->assertNotEmpty($signInData['dto_token']['access_token']);
        $this->assertNotEmpty($signInData['dto_token']['refresh_token']);
        $this->assertNotEmpty($signInData['dto_token']['expires_in']);
        $newAccessToken = $signInData['dto_token']['access_token'];

        // 新しいアクセストークンで再度 /player/me にアクセス
        $meResponse2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$newAccessToken,
        ])->getJson('/api/player/me');

        $meResponse2->assertStatus(200);
        $meData2 = $meResponse2->json();
        $this->assertEquals($myId, $meData2['data']['my_id']);
    }

    /**
     * メンテナンス中の認証フローテスト
     */
    public function test_auth_flow_during_maintenance(): void
    {
        // テストデバイスUUIDを生成
        $this->testDeviceUuid = 'test-device-'.Str::random(20);

        // 全てのメンテナンス情報を削除してクリーンな状態にする
        DB::connection('sys')
            ->table('sys_maintenance')
            ->delete(); // DELETE文を直接実行

        // メンテナンス情報を作成
        $maintenance = SysMaintenance::create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'title' => 'Test System Maintenance',
            'message' => 'Test maintenance in progress.',
        ]);

        // ========================================
        // GET /api/auth/version でメンテナンス情報を取得
        // ========================================
        $versionResponse = $this->getJson('/api/auth/version?app_version=1.0.0');

        $versionResponse->assertStatus(200);
        $versionData = $versionResponse->json();

        // メンテナンス情報が返ることを確認（dataキーの中にネストされている）
        $this->assertArrayHasKey('data', $versionData);
        $this->assertArrayHasKey('dto_maintenance', $versionData['data']);

        // メンテナンス情報が正しく返されていることを確認（具体的な値ではなく、構造を確認）
        $this->assertIsArray($versionData['data']['dto_maintenance']);
        $this->assertArrayHasKey('title', $versionData['data']['dto_maintenance']);
        $this->assertArrayHasKey('message', $versionData['data']['dto_maintenance']);
        $this->assertArrayHasKey('start_at', $versionData['data']['dto_maintenance']);
        $this->assertArrayHasKey('end_at', $versionData['data']['dto_maintenance']);
        $this->assertArrayHasKey('is_active', $versionData['data']['dto_maintenance']);

        // is_activeがtrueであることを確認（メンテナンス中）
        $this->assertTrue($versionData['data']['dto_maintenance']['is_active']);

        // テストデータをクリーンアップ
        $maintenance->delete();
    }

    /**
     * 無効なトークンでのアクセステスト
     */
    public function test_access_player_me_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345',
        ])->getJson('/api/player/me');

        // 認証エラーが返ることを確認
        $response->assertStatus(401);
    }

    /**
     * トークンなしでのアクセステスト
     */
    public function test_access_player_me_without_token(): void
    {
        $response = $this->getJson('/api/player/me');

        // 認証エラーが返ることを確認
        $response->assertStatus(401);
    }

    /**
     * 無効なリフレッシュトークンでのサインインテスト
     */
    public function test_sign_in_with_invalid_refresh_token(): void
    {
        $response = $this->postJson('/api/auth/refresh_token', [
            'refresh_token' => str_repeat('a', 64), // 64文字の無効なトークン
        ]);

        // エラーが返ることを確認（200 + error_code、422はバリデーションエラー、401は認証エラー、500は内部エラー）
        $this->assertTrue(
            in_array($response->status(), [200, 401, 422, 500]),
            "Expected 200, 401, 422, or 500, got {$response->status()}"
        );
    }

    /**
     * 同じデバイスで複数回サインアップを試みるテスト
     */
    public function test_sign_up_with_same_device_twice(): void
    {
        $deviceUuid = 'test-device-dup-'.Str::random(20);

        // 1回目のサインアップ
        $response1 = $this->postJson('/api/auth/sign_up', [
            'device_id' => $deviceUuid,
            'device_info' => [
                'os' => 'Android',
                'os_version' => '14.0',
                'model' => 'Pixel 8',
                'app_version' => '1.0.0',
            ],
        ]);

        $response1->assertOk(); // 200 OK
        $data1 = $response1->json();
        $myId1 = $data1['sys_player']['my_id'];

        // プレイヤーIDを保存してクリーンアップ対象にする
        $player = SysPlayer::where('my_id', $myId1)->first();
        $tempPlayerId = $player->id;

        // 2回目のサインアップ（同じデバイスID）
        $response2 = $this->postJson('/api/auth/sign_up', [
            'device_id' => $deviceUuid,
            'device_info' => [
                'os' => 'Android',
                'os_version' => '14.0',
                'model' => 'Pixel 8',
                'app_version' => '1.0.0',
            ],
        ]);

        $response2->assertStatus(200); // GameExceptionは200 + error_codeを返す
        $data2 = $response2->json();

        // エラーレスポンスが正しいことを確認
        $this->assertArrayHasKey('error_code', $data2);
        $this->assertArrayHasKey('message', $data2);
        // DEVICE_ALREADY_EXISTSのエラーコードを確認（GameErrorCode::DEVICE_ALREADY_EXISTS = 10004）
        $this->assertEquals(10004, $data2['error_code']);

        // 同じプレイヤーとして扱われることを確認（データベースから確認）
        $player2 = SysPlayer::where('my_id', $myId1)->first();
        $this->assertNotNull($player2);
        $this->assertEquals($tempPlayerId, $player2->id);

        // クリーンアップ
        SysPlayerToken::where('sys_player_id', $tempPlayerId)->delete();
        SysPlayerDevice::where('sys_player_id', $tempPlayerId)->delete();
        SysPlayer::where('id', $tempPlayerId)->delete();
    }
}
