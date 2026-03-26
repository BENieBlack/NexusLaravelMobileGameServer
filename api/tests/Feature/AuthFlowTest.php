<?php

namespace Tests\Feature;

use App\Models\Sys\SysDeployAsset;
use App\Models\Sys\SysDeployMaster;
use App\Models\Sys\SysMaintenance;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AuthFlowTest
 * 
 * 認証フロー全体の統合テスト
 * version → sign_up → sign_in → player/me の一連の流れをテスト
 */
class AuthFlowTest extends TestCase
{
    private string $testDeviceUuid;
    private ?int $testPlayerId = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // テスト用のユニークなデバイスUUIDを生成
        $this->testDeviceUuid = 'test-device-' . Str::random(20);
        
        // テスト用のデプロイ情報を作成（存在しない場合のみ）
        // deploy_keyは整数値（YYYYMMDDn形式）
        $testDeployKey = (int)(now()->format('Ymd') . '1'); // 例: 202602231
        $testHash = hash('sha256', 'test-deploy-' . now()->timestamp);
        
        if (!SysDeployMaster::where('deploy_key', $testDeployKey)->exists()) {
            SysDeployMaster::create([
                'deploy_key' => $testDeployKey,
                'hash' => $testHash,
                'deploy_date' => now(),
                'deploy_count' => 1,
            ]);
        }

        if (!SysDeployAsset::where('deploy_key', $testDeployKey)->exists()) {
            SysDeployAsset::create([
                'deploy_key' => $testDeployKey,
                'hash' => $testHash,
                'deploy_date' => now(),
                'deploy_count' => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        // テストで作成したデータをクリーンアップ
        if ($this->testPlayerId) {
            SysPlayerToken::where('sys_player_id', $this->testPlayerId)->delete();
            SysPlayerDevice::where('sys_player_id', $this->testPlayerId)->delete();
            SysPlayer::where('id', $this->testPlayerId)->delete();
        }
        
        // テスト用のメンテナンスデータを削除
        SysMaintenance::where('title', 'LIKE', 'Test%')->delete();
        
        parent::tearDown();
    }

    /**
     * 正常な認証フロー全体のテスト（メンテナンスなし）
     */
    public function test_complete_auth_flow_without_maintenance(): void
    {
        // ========================================
        // Step 1: GET /auth/version
        // ========================================
        $versionResponse = $this->getJson('/api/auth/version?app_version=1.0.0');

        $versionResponse->assertStatus(200);
        $versionResponse->assertJsonStructure([
            'needs_update',
        ]);

        // メンテナンス情報がないことを確認
        $versionData = $versionResponse->json();
        $this->assertArrayNotHasKey('maintenance', $versionData);

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

        if ($signUpResponse->status() !== 201) {
            dump('SignUp Response Status: ' . $signUpResponse->status());
            dump('SignUp Response Body: ' . $signUpResponse->getContent());
        }

        $signUpResponse->assertStatus(201);
        $signUpResponse->assertJsonStructure([
            'my_id',
            'access_token',
            'refresh_token',
            'expires_in',
        ]);

        $signUpData = $signUpResponse->json();
        $this->assertNotEmpty($signUpData['access_token']);
        $this->assertNotEmpty($signUpData['refresh_token']);
        $this->assertNotEmpty($signUpData['my_id']);

        // トークンを保存
        $accessToken = $signUpData['access_token'];
        $refreshToken = $signUpData['refresh_token'];
        $myId = $signUpData['my_id'];

        // クリーンアップのためにプレイヤーIDを保存
        $player = SysPlayer::where('my_id', $myId)->first();
        $this->testPlayerId = $player->id;

        // ========================================
        // Step 3: GET /player/me (アクセストークン使用)
        // ========================================
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->getJson('/api/player/me');

        $meResponse->assertStatus(200);
        $meResponse->assertJsonStructure([
            'my_id',
            'name',
        ]);

        $meData = $meResponse->json();
        $this->assertEquals($myId, $meData['my_id']);

        // ========================================
        // Step 4: POST /auth/sign_in (リフレッシュトークンで再ログイン)
        // ========================================
        $signInResponse = $this->postJson('/api/auth/sign_in', [
            'refresh_token' => $refreshToken,
        ]);

        $signInResponse->assertStatus(200);
        $signInResponse->assertJsonStructure([
            'access_token',
            'refresh_token',
            'expires_in',
        ]);

        $signInData = $signInResponse->json();
        $this->assertNotEmpty($signInData['access_token']);
        $this->assertNotEmpty($signInData['refresh_token']);
        $this->assertNotEmpty($signInData['expires_in']);

        // 新しいアクセストークンで再度 /player/me にアクセス
        $newAccessToken = $signInData['access_token'];
        $meResponse2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $newAccessToken,
        ])->getJson('/api/player/me');

        $meResponse2->assertStatus(200);
        $meData2 = $meResponse2->json();
        $this->assertEquals($myId, $meData2['my_id']);
    }

    /**
     * メンテナンス中の認証フローテスト
     */
    public function test_auth_flow_during_maintenance(): void
    {
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
        
        // メンテナンス情報が返ることを確認
        $this->assertArrayHasKey('maintenance', $versionData);
        $this->assertEquals('Test System Maintenance', $versionData['maintenance']['title']);
        $this->assertEquals('Test maintenance in progress.', $versionData['maintenance']['message']);
        $this->assertNotNull($versionData['maintenance']['start_at']);
        $this->assertNotNull($versionData['maintenance']['end_at']);

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
        $response = $this->postJson('/api/auth/sign_in', [
            'refresh_token' => str_repeat('a', 64), // 64文字の無効なトークン
        ]);

        // エラーが返ることを確認
        $response->assertStatus(401);
    }

    /**
     * 同じデバイスで複数回サインアップを試みるテスト
     */
    public function test_sign_up_with_same_device_twice(): void
    {
        $deviceUuid = 'test-device-dup-' . Str::random(20);

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

        $response1->assertStatus(201); // 新規作成なので201
        $data1 = $response1->json();
        $myId1 = $data1['my_id']; // my_idは直接トップレベルにある

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

        $response2->assertStatus(200); // 既存プレイヤーなので200
        $data2 = $response2->json();
        $myId2 = $data2['my_id']; // my_idは直接トップレベルにある

        // 同じプレイヤーとして扱われることを確認
        $this->assertEquals($myId1, $myId2);

        // クリーンアップ
        SysPlayerToken::where('sys_player_id', $tempPlayerId)->delete();
        SysPlayerDevice::where('sys_player_id', $tempPlayerId)->delete();
        SysPlayer::where('id', $tempPlayerId)->delete();
    }
}
