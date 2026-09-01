<?php

namespace Tests\Unit\Traits;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Traits\RequiresAuthenticationTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RequiresAuthenticationTrait のテスト
 *
 * 認証済みプレイヤーIDの取り出しを共通化するトレイト。
 * 3つのUseCaseが入口で使っている。
 *
 * 認証を通していないリクエストが素通りしないことが要点。
 * 素通りするとプレイヤーIDが不定のまま後続の処理へ流れる。
 */
class RequiresAuthenticationTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // トレイトのメソッドはprotectedなので、公開する薄い入れ物を用意する
        $this->subject = new class
        {
            use RequiresAuthenticationTrait;

            public function orFail(mixed $request): int
            {
                return $this->resolveAuthenticatedPlayerIdOrFail($request);
            }

            public function orNull(mixed $request): ?int
            {
                return $this->resolveAuthenticatedPlayerId($request);
            }
        };
    }

    #[Test]
    public function 認証済みならプレイヤーidを返す(): void
    {
        $this->assertSame(123, $this->subject->orFail($this->request(123)));
        $this->assertSame(123, $this->subject->orNull($this->request(123)));
    }

    #[Test]
    public function 認証されていなければ例外になる(): void
    {
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Player ID not found in request');

        $this->subject->orFail($this->request(null));
    }

    #[Test]
    public function 認証失敗の例外は認証エラーの区分になる(): void
    {
        try {
            $this->subject->orFail($this->request(null));
            $this->fail('例外が投げられていない');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::AUTHENTICATION_FAILED, $e->getErrorCode());
        }
    }

    #[Test]
    public function プレイヤーidが0なら未認証として扱う(): void
    {
        // 0を通すと、存在しないプレイヤーとして後続が動いてしまう
        $this->expectException(GameException::class);

        $this->subject->orFail($this->request(0));
    }

    #[Test]
    public function 認証に対応しないリクエストは内部エラーになる(): void
    {
        try {
            $this->subject->orFail(new \stdClass);
            $this->fail('例外が投げられていない');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::INTERNAL_ERROR, $e->getErrorCode());
            $this->assertSame('Request does not support authentication', $e->getMessage());
        }
    }

    #[Test]
    public function null版は認証されていなくてもnullを返す(): void
    {
        $this->assertNull($this->subject->orNull($this->request(null)));
        $this->assertNull($this->subject->orNull(new \stdClass), '対応しないリクエストでも投げない');
    }

    private function request(?int $playerId): object
    {
        return new class($playerId)
        {
            public function __construct(private readonly ?int $playerId) {}

            public function resolveAuthenticatedPlayerId(): ?int
            {
                return $this->playerId;
            }
        };
    }
}
