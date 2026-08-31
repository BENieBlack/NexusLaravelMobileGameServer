<?php

namespace Tests\Unit\Models;

use App\Models\Sys\SysPlayerToken;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysPlayerToken のテスト
 *
 * リフレッシュトークンの有効判定は認証そのもの。
 * 失効させたトークンや期限切れを「有効」と返すとログインを乗っ取られる。
 *
 * 判定は revoked_at と expires_at の2つで、片方だけ見ると穴になる。
 */
class SysPlayerTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    // ========================================
    // 有効判定
    // ========================================

    #[Test]
    public function 期限内で失効していなければ有効(): void
    {
        $token = $this->makeToken(expiresAt: '2026-03-16 12:00:00');

        $this->assertTrue($token->isValid());
        $this->assertFalse($token->isExpired());
    }

    #[Test]
    public function 期限切れは無効(): void
    {
        $token = $this->makeToken(expiresAt: '2026-03-15 11:59:59');

        $this->assertFalse($token->isValid());
        $this->assertTrue($token->isExpired());
    }

    #[Test]
    public function 期限ちょうどは無効(): void
    {
        // 未来であることを求める。同時刻は使わせない
        $token = $this->makeToken(expiresAt: '2026-03-15 12:00:00');

        $this->assertFalse($token->isValid());
        $this->assertTrue($token->isExpired());
    }

    #[Test]
    public function 失効させたトークンは期限内でも無効(): void
    {
        // ログアウトや他端末ログインで失効させたものを使わせない
        $token = $this->makeToken(expiresAt: '2026-03-16 12:00:00', revokedAt: '2026-03-15 10:00:00');

        $this->assertFalse($token->isValid());
        $this->assertFalse($token->isExpired(), '失効と期限切れは別の状態');
    }

    #[Test]
    public function 失効させると有効でなくなる(): void
    {
        $token = $this->makeToken(expiresAt: '2026-03-16 12:00:00');
        $this->assertTrue($token->isValid());

        $token->revoke();

        $this->assertNotNull($token->getRevokedAt());
        $this->assertFalse($token->isValid());
    }

    // ========================================
    // 値の出し入れ
    // ========================================

    #[Test]
    public function 値を設定して読み出せる(): void
    {
        $token = new SysPlayerToken;
        $token->setSysPlayerId(42);
        $token->setSysPlayerDeviceId(7);
        $token->setRefreshTokenHash('hash-001');
        $token->setExpiresAt('2026-04-01 00:00:00');

        $this->assertSame(42, $token->getSysPlayerId());
        $this->assertSame(7, $token->getSysPlayerDeviceId());
        $this->assertSame('hash-001', $token->getRefreshTokenHash());
        $this->assertSame('2026-04-01 00:00:00', $token->getExpiresAt());
        $this->assertNull($token->getRevokedAt());
    }

    #[Test]
    public function 失効日時は明示的に外せる(): void
    {
        $token = $this->makeToken(expiresAt: '2026-03-16 12:00:00', revokedAt: '2026-03-15 10:00:00');

        $token->setRevokedAt(null);

        $this->assertNull($token->getRevokedAt());
        $this->assertTrue($token->isValid());
    }

    #[Test]
    public function 日時はdatetimeでも渡せる(): void
    {
        $token = new SysPlayerToken;
        $token->setExpiresAt(new \DateTimeImmutable('2026-04-01 00:00:00'));

        $this->assertSame('2026-04-01 00:00:00', $token->getExpiresAt());
    }

    // ========================================
    // 認証パッケージ向けの入口
    // ========================================

    #[Test]
    public function 認証パッケージの契約を満たす(): void
    {
        // NexusAuth は TokenModelInterface 越しにしか触らない
        $token = $this->makeToken(expiresAt: '2026-03-16 12:00:00');

        $this->assertSame(42, $token->getPlayerId());
        $this->assertSame('hash-001', $token->getRefreshToken());
        $this->assertSame('2026-03-16 12:00:00', $token->getExpiresAt());
    }

    #[Test]
    public function レスポンス用の配列ではidに接頭辞が付く(): void
    {
        // 応答のIDキーはどのテーブルのIDか分かる名前にする
        $token = $this->makeToken(expiresAt: '2026-03-16 12:00:00');
        $token->setAttribute('id', 99);

        $array = $token->toResponseArray();

        $this->assertSame(99, $array['sys_player_token_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    private function makeToken(string $expiresAt, ?string $revokedAt = null): SysPlayerToken
    {
        $token = new SysPlayerToken;
        $token->setSysPlayerId(42);
        $token->setSysPlayerDeviceId(7);
        $token->setRefreshTokenHash('hash-001');
        $token->setExpiresAt($expiresAt);
        $token->setRevokedAt($revokedAt);

        return $token;
    }
}
