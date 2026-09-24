<?php

namespace Tests\Feature\Guild;

use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 認証不要なギルド参照系のレート制限テスト
 *
 * ギルドの一覧・詳細・メンバー一覧は加入前に見られる必要があるため
 * 認証を掛けていない。制限が無いと繰り返し叩くだけで
 * 他プレイヤーの所属を収集できてしまうため、IP単位の上限を固定する。
 */
class ThrottlePublicReadTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const RATE_LIMIT_KEY = 'throttle_public_read:guild_read:ip:127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(self::RATE_LIMIT_KEY);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear(self::RATE_LIMIT_KEY);

        parent::tearDown();
    }

    #[Test]
    public function 認証なしでもギルド一覧は取得できる(): void
    {
        $response = $this->getJson('/api/guild/list');

        $response->assertStatus(200);
        $response->assertJsonStructure(['guilds']);
    }

    #[Test]
    public function 上限を超えると429になる(): void
    {
        $max = (int) config('security.throttle_public_read.max_attempts_per_ip', 60);

        for ($i = 0; $i < $max; $i++) {
            $response = $this->getJson('/api/guild/list');
            $this->assertNotSame(429, $response->status(), ($i + 1).'回目で制限されるべきではない');
        }

        $response = $this->getJson('/api/guild/list');

        $response->assertStatus(429);
        $response->assertJsonStructure(['error', 'message', 'retry_after']);
        $this->assertSame('TOO_MANY_REQUESTS', $response->json('error'));
    }

    #[Test]
    public function 参照系の3本は同じ上限を共有する(): void
    {
        // エンドポイントごとに数えると、3倍叩けてしまう
        $max = (int) config('security.throttle_public_read.max_attempts_per_ip', 60);

        for ($i = 0; $i < $max; $i++) {
            $this->getJson('/api/guild/list');
        }

        $this->getJson('/api/guild/detail?sys_guild_id=1')->assertStatus(429);
        $this->getJson('/api/guild/member/list?sys_guild_id=1')->assertStatus(429);
    }

    #[Test]
    public function 残り回数をヘッダーで返す(): void
    {
        $max = (int) config('security.throttle_public_read.max_attempts_per_ip', 60);

        $response = $this->getJson('/api/guild/list');

        $response->assertHeader('X-RateLimit-Limit', (string) $max);
        $response->assertHeader('X-RateLimit-Remaining', (string) ($max - 1));
    }

    #[Test]
    public function 設定で無効にできる(): void
    {
        config(['security.throttle_public_read.enabled' => false]);

        $max = (int) config('security.throttle_public_read.max_attempts_per_ip', 60);

        for ($i = 0; $i <= $max; $i++) {
            $this->getJson('/api/guild/list')->assertStatus(200);
        }
    }
}
