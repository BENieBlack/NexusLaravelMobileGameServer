<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * sign_in / refresh_token のレート制限テスト
 *
 * 認証情報を受け取るエンドポイントが無制限に叩けると
 * クレデンシャルスタッフィングの経路になるため、制限を固定する
 */
class ThrottleAuthTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('throttle_auth:sign_in:ip:127.0.0.1');
        RateLimiter::clear('throttle_auth:refresh_token:ip:127.0.0.1');
    }

    /**
     * 資格情報ごとの上限を超えると429になる
     */
    #[Test]
    public function test_sign_in_is_rate_limited_per_credential(): void
    {
        $max = (int) config('security.throttle_auth.max_attempts_per_credential', 10);

        $payload = [
            'device_id' => 'throttle-test-device',
            'device_info' => [
                'os' => 'iOS',
                'os_version' => '17.0',
                'model' => 'iPhone 15 Pro',
                'app_version' => '1.0.0',
            ],
        ];

        // 上限までは429以外が返る（認証失敗そのものは問わない）
        for ($i = 0; $i < $max; $i++) {
            $response = $this->postJson('/api/auth/sign_in', $payload);
            $this->assertNotSame(429, $response->status(), ($i + 1).'回目で制限されるべきではない');
        }

        // 上限を超えたら429
        $response = $this->postJson('/api/auth/sign_in', $payload);

        $response->assertStatus(429);
        $response->assertJsonStructure(['error', 'message', 'retry_after']);
        $this->assertSame('TOO_MANY_REQUESTS', $response->json('error'));
    }

    /**
     * refresh_tokenにも制限がかかる
     */
    #[Test]
    public function test_refresh_token_is_rate_limited(): void
    {
        $max = (int) config('security.throttle_auth.max_attempts_per_credential', 10);

        // size:64 のバリデーションを満たす固定トークン
        $payload = ['refresh_token' => str_repeat('a', 64)];

        for ($i = 0; $i < $max; $i++) {
            $response = $this->postJson('/api/auth/refresh_token', $payload);
            $this->assertNotSame(429, $response->status());
        }

        $this->postJson('/api/auth/refresh_token', $payload)->assertStatus(429);
    }

    /**
     * 資格情報が異なれば独立して数えられる
     */
    #[Test]
    public function test_different_credentials_are_counted_separately(): void
    {
        $max = (int) config('security.throttle_auth.max_attempts_per_credential', 10);

        $makePayload = fn (string $deviceId) => [
            'device_id' => $deviceId,
            'device_info' => [
                'os' => 'iOS',
                'os_version' => '17.0',
                'model' => 'iPhone 15 Pro',
                'app_version' => '1.0.0',
            ],
        ];

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/api/auth/sign_in', $makePayload('device-a'));
        }

        $this->postJson('/api/auth/sign_in', $makePayload('device-a'))->assertStatus(429);

        // 別デバイスはまだ制限にかからない（IP上限には達していない範囲）
        $other = $this->postJson('/api/auth/sign_in', $makePayload('device-b'));
        $this->assertNotSame(429, $other->status());
    }
}
