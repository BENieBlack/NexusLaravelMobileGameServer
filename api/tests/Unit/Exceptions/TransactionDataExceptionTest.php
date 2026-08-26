<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\TransactionDataException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TransactionDataException のテスト
 *
 * リソースごとのファクトリメソッドが、対応するエラーコードと
 * 原因が分かるメッセージを返すことを確認する。
 * エラーコードはクライアントが分岐に使うため、値そのものを固定する。
 */
class TransactionDataExceptionTest extends TestCase
{
    #[Test]
    public function プレイヤーが見つからない場合(): void
    {
        $exception = TransactionDataException::player(42);

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertSame(GameErrorCode::PLAYER_NOT_FOUND, $exception->getErrorCode());
        $this->assertSame('Player not found: 42', $exception->getMessage());
    }

    #[Test]
    public function プレイヤーをuuidや_my_idで引けなかった場合も同じコードになる(): void
    {
        $byUuid = TransactionDataException::playerByUuid('uuid-123');
        $byMyId = TransactionDataException::playerByMyId('TEST0001');

        $this->assertSame(GameErrorCode::PLAYER_NOT_FOUND, $byUuid->getErrorCode());
        $this->assertSame(GameErrorCode::PLAYER_NOT_FOUND, $byMyId->getErrorCode());

        // 何で引いて見つからなかったかがメッセージから分かる
        $this->assertSame('Player not found by UUID: uuid-123', $byUuid->getMessage());
        $this->assertSame('Player not found by My ID: TEST0001', $byMyId->getMessage());
    }

    #[Test]
    public function 所持データごとに対応するエラーコードを返す(): void
    {
        $cases = [
            [TransactionDataException::unit(7), GameErrorCode::UNIT_NOT_FOUND, 'Unit not found: 7'],
            [TransactionDataException::item('item_potion'), GameErrorCode::ITEM_NOT_ENOUGH, 'Item not found: item_potion'],
            [TransactionDataException::equipment(9), GameErrorCode::EQUIPMENT_NOT_FOUND, 'Equipment not found: 9'],
            [TransactionDataException::wallet('gold'), GameErrorCode::WALLET_NOT_FOUND, 'Wallet not found for item: gold'],
            [TransactionDataException::stamina(3), GameErrorCode::STAMINA_NOT_ENOUGH, 'Stamina data not found: player=3'],
        ];

        foreach ($cases as [$exception, $expectedCode, $expectedMessage]) {
            $this->assertSame($expectedCode, $exception->getErrorCode(), $expectedMessage);
            $this->assertSame($expectedMessage, $exception->getMessage());
        }
    }

    #[Test]
    public function ダイヤはプラットフォームもメッセージに含む(): void
    {
        $exception = TransactionDataException::diamond(5, 'AppStore');

        $this->assertSame(GameErrorCode::DIAMOND_NOT_ENOUGH, $exception->getErrorCode());
        $this->assertSame('Diamond data not found: player=5, platform=AppStore', $exception->getMessage());
    }

    #[Test]
    public function 汎用の生成では種別とidを埋め込む(): void
    {
        $withIntId = TransactionDataException::generic('unit', 12);
        $withStringId = TransactionDataException::generic('item', 'item_001');

        $this->assertSame(GameErrorCode::INTERNAL_ERROR, $withIntId->getErrorCode());
        $this->assertSame('Transaction data not found: unit (ID: 12)', $withIntId->getMessage());
        $this->assertSame('Transaction data not found: item (ID: item_001)', $withStringId->getMessage());
    }

    #[Test]
    public function レスポンス用の配列にエラーコードとメッセージが入る(): void
    {
        $array = TransactionDataException::unit(7)->toArray();

        $this->assertSame(GameErrorCode::UNIT_NOT_FOUND, $array['error_code']);
        $this->assertSame('Unit not found: 7', $array['message']);
    }
}
