<?php

namespace Tests\Unit\Domain\Guild\Support;

use App\Domain\Guild\Support\GuildExceptionTranslator;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use NexusGuild\Exceptions\GuildException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GuildExceptionTranslator のテスト
 *
 * パッケージは種類だけを持った例外を投げ、クライアントへ返すコードは
 * ここで決める。以前は各UseCaseがメッセージの部分一致で判定していたため、
 * パッケージ側の文言を変えるとエラーコードが黙って変わる状態だった。
 *
 * 同じ種類でも操作によって返すコードが変わる（作成時の「既に存在する」は
 * ギルド名の重複、申請時は申請の重複）ので、操作ごとに入口を分けている。
 */
class GuildExceptionTranslatorTest extends TestCase
{
    #[Test]
    public function 例外が出なければ戻り値をそのまま返す(): void
    {
        $this->assertSame('ok', GuildExceptionTranslator::forCreate(fn () => 'ok'));
        $this->assertSame('ok', GuildExceptionTranslator::forRead(fn () => 'ok'));
    }

    #[Test]
    public function ギルド以外の例外は素通しする(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('db is down');

        GuildExceptionTranslator::forCreate(fn () => throw new \RuntimeException('db is down'));
    }

    #[Test]
    public function どの操作でも共通の対応がある(): void
    {
        $expected = [
            'guildNotFound' => GameErrorCode::GUILD_NOT_FOUND,
            'guildFull' => GameErrorCode::GUILD_FULL,
            'alreadyInGuild' => GameErrorCode::PLAYER_ALREADY_IN_GUILD,
            'notInGuild' => GameErrorCode::PLAYER_NOT_IN_GUILD,
            'applyNotFound' => GameErrorCode::GUILD_APPLY_NOT_FOUND,
            'invalidStatus' => GameErrorCode::GUILD_INVALID_STATUS,
            'masterCannotLeave' => GameErrorCode::GUILD_MASTER_CANNOT_LEAVE,
            'memberNotFound' => GameErrorCode::GUILD_MEMBER_NOT_FOUND,
        ];

        foreach ($expected as $factory => $errorCode) {
            $this->assertTranslatesTo(
                fn () => GuildExceptionTranslator::forApplyAccept(
                    fn () => throw $this->makeException($factory)
                ),
                $errorCode,
                $factory
            );
        }
    }

    #[Test]
    public function 権限エラーは共通のコードになる(): void
    {
        $this->assertTranslatesTo(
            fn () => GuildExceptionTranslator::forApplyReject(
                fn () => throw GuildException::permissionDenied(1, 'reject guild apply')
            ),
            GameErrorCode::GUILD_PERMISSION_DENIED
        );
    }

    #[Test]
    public function メッセージはそのまま引き継ぐ(): void
    {
        try {
            GuildExceptionTranslator::forCreate(fn () => throw GuildException::guildFull(10));
            $this->fail('翻訳されなかった');
        } catch (GameException $e) {
            $this->assertSame('Guild is full: 10', $e->getMessage());
        }
    }

    // ========================================
    // 操作ごとに変わる対応
    // ========================================

    #[Test]
    public function 作成時の既に存在するはギルド名の重複(): void
    {
        $this->assertTranslatesTo(
            fn () => GuildExceptionTranslator::forCreate(
                fn () => throw GuildException::guildNameAlreadyExists('テストギルド')
            ),
            GameErrorCode::GUILD_NAME_ALREADY_EXISTS
        );
    }

    #[Test]
    public function 申請時の既に存在するは申請の重複(): void
    {
        // 同じ「既に存在する」でも意味が違う。メッセージで判定していた頃は
        // ここが取り違えられる危険があった
        $this->assertTranslatesTo(
            fn () => GuildExceptionTranslator::forApplySend(
                fn () => throw GuildException::applyAlreadyExists(10, 1)
            ),
            GameErrorCode::GUILD_APPLY_ALREADY_EXISTS
        );
    }

    #[Test]
    public function 操作ごとの特例は他の入口へ持ち込まない(): void
    {
        // 申請の重複を作成の入口で受けても、ギルド名の重複にはしない
        $this->assertTranslatesTo(
            fn () => GuildExceptionTranslator::forCreate(
                fn () => throw GuildException::applyAlreadyExists(10, 1)
            ),
            GameErrorCode::GUILD_CREATE_FAILED
        );

        // ギルド名の重複を申請の入口で受けても、申請の重複にはしない
        $this->assertTranslatesTo(
            fn () => GuildExceptionTranslator::forApplySend(
                fn () => throw GuildException::guildNameAlreadyExists('テストギルド')
            ),
            GameErrorCode::GUILD_APPLY_FAILED
        );
    }

    // ========================================
    // 対応が無い場合
    // ========================================

    #[Test]
    public function 対応が無ければ操作ごとの既定コードになる(): void
    {
        $expected = [
            'forCreate' => GameErrorCode::GUILD_CREATE_FAILED,
            'forApplySend' => GameErrorCode::GUILD_APPLY_FAILED,
            'forApplyAccept' => GameErrorCode::GUILD_APPLY_ACCEPT_FAILED,
            'forApplyReject' => GameErrorCode::GUILD_APPLY_REJECT_FAILED,
            'forLeave' => GameErrorCode::GUILD_LEAVE_FAILED,
        ];

        foreach ($expected as $method => $errorCode) {
            $this->assertTranslatesTo(
                fn () => GuildExceptionTranslator::{$method}(
                    fn () => throw GuildException::invalidRole('no_such_role')
                ),
                $errorCode,
                $method
            );
        }
    }

    #[Test]
    public function 参照系の想定外はサーバ側の問題として扱う(): void
    {
        // 更新を伴わないので、クライアントに直せることは無い
        $this->assertTranslatesTo(
            fn () => GuildExceptionTranslator::forRead(
                fn () => throw GuildException::invalidRole('no_such_role')
            ),
            GameErrorCode::INTERNAL_ERROR
        );
    }

    #[Test]
    public function 未知のコードも既定へ落とす(): void
    {
        // パッケージ側で種類が増えても500にはしない
        $this->assertTranslatesTo(
            fn () => GuildExceptionTranslator::forCreate(
                fn () => throw new GuildException('unknown reason', 9999)
            ),
            GameErrorCode::GUILD_CREATE_FAILED
        );
    }

    private function makeException(string $factory): GuildException
    {
        return match ($factory) {
            'guildNotFound' => GuildException::guildNotFound(10),
            'guildFull' => GuildException::guildFull(10),
            'alreadyInGuild' => GuildException::alreadyInGuild(1),
            'notInGuild' => GuildException::notInGuild(1),
            'applyNotFound' => GuildException::applyNotFound(100),
            'invalidStatus' => GuildException::invalidStatus('accepted'),
            'masterCannotLeave' => GuildException::masterCannotLeave(1),
            'memberNotFound' => GuildException::memberNotFound(1),
        };
    }

    private function assertTranslatesTo(callable $run, int $expectedErrorCode, string $label = ''): void
    {
        try {
            $run();
            $this->fail("翻訳されなかった: {$label}");
        } catch (GameException $e) {
            $this->assertSame($expectedErrorCode, $e->getErrorCode(), $label);
        }
    }
}
