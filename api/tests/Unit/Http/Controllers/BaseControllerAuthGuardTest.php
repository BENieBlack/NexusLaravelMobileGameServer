<?php

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Controllers\_BaseController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * _BaseController::requireAuthenticatedPlayerId のテスト
 *
 * 認証チェックは auth.token ミドルウェアが先に401を返すため、
 * HTTP経由ではここまで届かない。認証グループの外にルートを
 * 置いてしまったときの保険なので、直接呼んで確かめる。
 */
class BaseControllerAuthGuardTest extends TestCase
{
    private ExposedController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ExposedController;
    }

    #[Test]
    public function プレイヤーidがあればそのまま返る(): void
    {
        $this->assertSame(123, $this->controller->requirePlayerId(123));
    }

    #[Test]
    public function プレイヤーidが無ければ認証エラーになる(): void
    {
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Player ID not found in request');

        $this->controller->requirePlayerId(null);
    }

    #[Test]
    public function 認証エラーのエラーコードを返す(): void
    {
        try {
            $this->controller->requirePlayerId(null);
            $this->fail('例外が飛ばなかった');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::AUTHENTICATION_FAILED, $e->getErrorCode());
        }
    }

    #[Test]
    public function プレイヤーidの0は認証されていないものとして扱う(): void
    {
        // sys_player.id は1始まりなので0は入り得ない。
        // 未設定が0に化けて通ってしまうより弾く方が安全
        $this->expectException(GameException::class);

        $this->controller->requirePlayerId(0);
    }
}

/**
 * protectedメソッドを呼ぶためだけの具象クラス
 */
class ExposedController extends _BaseController
{
    public function requirePlayerId(?int $sysPlayerId): int
    {
        return $this->requireAuthenticatedPlayerId($sysPlayerId);
    }
}
