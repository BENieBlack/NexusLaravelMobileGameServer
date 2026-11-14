<?php

namespace Tests\Feature\Middleware;

use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ResolvePlatform（X-Platform ヘッダーの検証）のルート単位のテスト
 *
 * routes/api.php の maintenance グループに付いているかを、実際のルートを叩いて確かめる。
 * TestCase は既定で X-Platform: Apple を付けるので、各テストで必要に応じて外す。
 */
class ResolvePlatformTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const ADMIN_TOKEN = 'test-admin-token-0123456789abcdef';

    private const SIGN_UP_BODY = [
        'device_id' => 'platform-test-device',
        'device_info' => ['os' => 'iOS', 'os_version' => '17.0', 'model' => 'iPhone', 'app_version' => '1.0.0'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // 前のテストの platform が同一プロセスに残らないようにする
        ApiSession::clearForTest();
    }

    protected function tearDown(): void
    {
        ApiSession::clearForTest();
        parent::tearDown();
    }

    /**
     * 欠落・空・不正値でゲームAPIが 299 / 99003 になること、メッセージで区別できること
     *
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function invalidPlatformProvider(): array
    {
        return [
            '欠落' => [null, 'X-Platform header is required.'],
            '空文字' => ['', 'X-Platform header is required.'],
            '小文字 apple' => ['apple', 'X-Platform must be one of: Apple, Google.'],
            '小文字 google' => ['google', 'X-Platform must be one of: Apple, Google.'],
            'iOS' => ['iOS', 'X-Platform must be one of: Apple, Google.'],
            '不明な値 Windows' => ['Windows', 'X-Platform must be one of: Apple, Google.'],
            '前後に空白' => [' Apple', 'X-Platform must be one of: Apple, Google.'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPlatformProvider')]
    public function 欠落と不正値は299と99003で返りメッセージで区別できる(?string $value, string $message): void
    {
        $this->applyPlatform($value);

        $response = $this->postJson('/api/auth/sign_up', self::SIGN_UP_BODY);

        $response->assertStatus(299);
        $response->assertExactJson([
            'error_code' => GameErrorCode::INVALID_PLATFORM,
            'message' => $message,
        ]);
        $this->assertSame(99003, $response->json('error_code'));
        $this->assertFalse(ApiSession::hasPlatform());
    }

    #[Test]
    public function 本番でもメッセージはマスクされない(): void
    {
        Config::set('app.env', 'production');
        $this->withoutHeader('X-Platform');

        $this->postJson('/api/auth/sign_in', [])
            ->assertStatus(299)
            ->assertJsonPath('message', 'X-Platform header is required.');
    }

    #[Test]
    public function apple_と_google_は通り_api_sessionに載る(): void
    {
        foreach (['Apple', 'Google'] as $platform) {
            ApiSession::clearForTest();
            $this->withHeader('X-Platform', $platform);

            // 認証前の sign_up でも platform が読めること（認証より前に載せる）
            $this->signUpPlayer();

            $this->assertTrue(ApiSession::hasPlatform());
            $this->assertSame($platform, ApiSession::getPlatform());
        }
    }

    #[Test]
    public function 認証付きのpost_auth_versionも必須で_get_auth_versionだけ不要(): void
    {
        $signedUp = $this->signUpPlayer();
        $headers = $this->authHeaders($signedUp['token']);
        $body = ['deploy_version' => config('app.deploy_version', 202601010)];

        // POST（認証済み）は maintenance グループ内なので必須
        $this->withoutHeader('X-Platform');
        $this->withHeaders($headers)->postJson('/api/auth/version', $body)
            ->assertStatus(299)
            ->assertJsonPath('error_code', GameErrorCode::INVALID_PLATFORM);

        $this->withHeader('X-Platform', 'Google');
        $this->withHeaders($headers)->postJson('/api/auth/version', $body)->assertOk();

        // GET は除外
        ApiSession::clearForTest();
        $this->withoutHeader('X-Platform');
        $this->getJson('/api/auth/version?app_version=1.0.0')->assertOk();
    }

    #[Test]
    public function 除外ルートはヘッダー無しでもplatformエラーにならない(): void
    {
        Config::set('auth.admin_token', self::ADMIN_TOKEN);
        Config::set('auth.admin_allowed_ips', null);
        $this->withoutHeader('X-Platform');

        $this->getJson('/api/maintenance/status')->assertOk();
        $this->get('/up')->assertOk();

        // admin は管理者トークンが無ければ 401（platform の 299 ではない）
        $this->postJson('/api/admin/maintenance/start', [])->assertStatus(401);
        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson('/api/admin/maintenance/start', ['title' => 'メンテナンス', 'message' => '実施中です'])
            ->assertOk();
        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson('/api/admin/maintenance/end')
            ->assertOk();

        // broadcasting/auth はトークン認証（無ければ認証エラー。platform の 99003 ではない）
        $response = $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-x']);
        $this->assertNotSame(299, $response->getStatusCode());
        $this->assertNotSame(GameErrorCode::INVALID_PLATFORM, $response->json('error_code'));
    }

    #[Test]
    public function メンテ中はplatformの検証より先に503を返す(): void
    {
        Config::set('auth.admin_token', self::ADMIN_TOKEN);
        Config::set('auth.admin_allowed_ips', null);

        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson('/api/admin/maintenance/start', ['title' => 'メンテナンス', 'message' => '実施中です'])
            ->assertOk();

        try {
            // ヘッダーが無くても不正値でも 503（maintenance が先）
            $this->withoutHeader('X-Platform');
            $this->postJson('/api/auth/sign_in', [])->assertStatus(503);

            $this->withHeader('X-Platform', 'Windows');
            $this->postJson('/api/auth/sign_in', [])->assertStatus(503);
        } finally {
            $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))->postJson('/api/admin/maintenance/end');
        }
    }

    #[Test]
    public function 欠落は署名の検証より先に弾かれる(): void
    {
        // 署名ミドルウェアを有効に戻しても、platform の 299 が先に返る
        // （署名ヘッダーが無ければ 401 になるはずの経路）
        $this->withMiddleware();
        $this->withoutHeader('X-Platform');

        $this->postJson('/api/auth/sign_up', self::SIGN_UP_BODY)
            ->assertStatus(299)
            ->assertJsonPath('error_code', GameErrorCode::INVALID_PLATFORM);
    }

    /**
     * 値が null のときだけヘッダーを外す
     */
    private function applyPlatform(?string $value): void
    {
        if ($value === null) {
            $this->withoutHeader('X-Platform');

            return;
        }

        $this->withHeader('X-Platform', $value);
    }
}
