<?php

namespace Tests\Unit\Domain\Friend\Support;

use App\Domain\Friend\Support\FriendExceptionTranslator;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use NexusFriend\Exceptions\FriendException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FriendExceptionTranslator のテスト
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため種類だけを投げ、
 * クライアントへ返すコードはここで決める。取り違えると
 * 「既にフレンド」なのに「申請が見つからない」と表示されるような食い違いになる。
 *
 * 却下と削除だけは同じ種類でも別コードへ分けるため、専用の入口がある。
 */
class FriendExceptionTranslatorTest extends TestCase
{
    #[Test]
    public function 例外が出なければ戻り値をそのまま返す(): void
    {
        $this->assertSame('ok', FriendExceptionTranslator::translate(fn () => 'ok'));
        $this->assertSame('ok', FriendExceptionTranslator::translateForReject(fn () => 'ok'));
        $this->assertSame('ok', FriendExceptionTranslator::translateForDelete(fn () => 'ok'));
    }

    #[Test]
    public function フレンド以外の例外は素通しする(): void
    {
        // 翻訳の対象はパッケージの例外だけ。他は握り潰さない
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('db is down');

        FriendExceptionTranslator::translate(fn () => throw new \RuntimeException('db is down'));
    }

    #[Test]
    public function 種類ごとに対応するエラーコードへ翻訳する(): void
    {
        $expected = [
            'selfApply' => GameErrorCode::CANNOT_SEND_FRIEND_REQUEST_TO_SELF,
            'alreadyApplied' => GameErrorCode::FRIEND_REQUEST_ALREADY_EXISTS,
            'alreadyFriends' => GameErrorCode::FRIEND_ALREADY_EXISTS,
            'applyNotFound' => GameErrorCode::FRIEND_APPLY_NOT_FOUND,
            'notAuthorized' => GameErrorCode::NOT_AUTHORIZED_TO_ACCEPT,
            'alreadyAccepted' => GameErrorCode::FRIEND_APPLY_ALREADY_ACCEPTED,
            'alreadyRejected' => GameErrorCode::FRIEND_APPLY_ALREADY_REJECTED,
            'alreadyDeleted' => GameErrorCode::FRIEND_APPLY_ALREADY_DELETED,
            'friendNotFound' => GameErrorCode::FRIEND_NOT_FOUND,
        ];

        foreach ($expected as $factory => $errorCode) {
            $this->assertTranslatesTo(
                fn () => FriendExceptionTranslator::translate(
                    fn () => throw FriendException::{$factory}()
                ),
                $errorCode,
                $factory
            );
        }
    }

    #[Test]
    public function メッセージはそのまま引き継ぐ(): void
    {
        // 原因はパッケージ側の文言が持っているので落とさない
        try {
            FriendExceptionTranslator::translate(fn () => throw FriendException::alreadyFriends());
            $this->fail('翻訳されなかった');
        } catch (GameException $e) {
            $this->assertSame(FriendException::alreadyFriends()->getMessage(), $e->getMessage());
        }
    }

    #[Test]
    public function 未知の種類は申請が見つからない扱いにする(): void
    {
        // パッケージ側でコードが増えても、翻訳できずに500へ落とさない
        $this->assertTranslatesTo(
            fn () => FriendExceptionTranslator::translate(
                fn () => throw new FriendException('unknown reason', 9999)
            ),
            GameErrorCode::FRIEND_APPLY_NOT_FOUND
        );
    }

    // ========================================
    // 却下だけコードが分かれる
    // ========================================

    #[Test]
    public function 却下の権限エラーは却下専用のコードになる(): void
    {
        // 承認と却下でクライアントの出し分けが変わる
        $this->assertTranslatesTo(
            fn () => FriendExceptionTranslator::translateForReject(
                fn () => throw FriendException::notAuthorized()
            ),
            GameErrorCode::NOT_AUTHORIZED_TO_REJECT
        );
    }

    #[Test]
    public function 却下でも権限以外は共通の翻訳を使う(): void
    {
        $this->assertTranslatesTo(
            fn () => FriendExceptionTranslator::translateForReject(
                fn () => throw FriendException::alreadyAccepted()
            ),
            GameErrorCode::FRIEND_APPLY_ALREADY_ACCEPTED
        );
    }

    // ========================================
    // 削除だけコードが分かれる
    // ========================================

    #[Test]
    public function 削除の自分自身は削除専用のコードになる(): void
    {
        // 申請時の「自分には送れない」とは別の文言を出す
        $this->assertTranslatesTo(
            fn () => FriendExceptionTranslator::translateForDelete(
                fn () => throw FriendException::selfApply()
            ),
            GameErrorCode::CANNOT_DELETE_SELF
        );
    }

    #[Test]
    public function 削除でも自分自身以外は共通の翻訳を使う(): void
    {
        $this->assertTranslatesTo(
            fn () => FriendExceptionTranslator::translateForDelete(
                fn () => throw FriendException::friendNotFound()
            ),
            GameErrorCode::FRIEND_NOT_FOUND
        );
    }

    #[Test]
    public function 却下と削除の入口はそれぞれ相手の特例を持ち込まない(): void
    {
        // 却下で「自分自身」を投げても削除用のコードにはしない
        $this->assertTranslatesTo(
            fn () => FriendExceptionTranslator::translateForReject(
                fn () => throw FriendException::selfApply()
            ),
            GameErrorCode::CANNOT_SEND_FRIEND_REQUEST_TO_SELF
        );

        // 削除で「権限なし」を投げても却下用のコードにはしない
        $this->assertTranslatesTo(
            fn () => FriendExceptionTranslator::translateForDelete(
                fn () => throw FriendException::notAuthorized()
            ),
            GameErrorCode::NOT_AUTHORIZED_TO_ACCEPT
        );
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
