<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\MasterDataException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MasterDataException のテスト
 *
 * マスターの設定漏れやデプロイ差分で「あるはずのデータが無い」ときに出る。
 * どのマスターの何が無いのかがメッセージから分かることが要点で、
 * 分からないと本番で原因を追えない。
 *
 * 現時点で呼び出し元が無いファクトリも、使うときに壊れていないよう
 * ここで固定しておく。
 */
class MasterDataExceptionTest extends TestCase
{
    #[Test]
    public function ユニットマスターの不足を表せる(): void
    {
        $this->assertException(
            MasterDataException::unit('unit_ssr_001'),
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            'Master unit data not found: unit_ssr_001'
        );
    }

    #[Test]
    public function アイテムマスターの不足を表せる(): void
    {
        $this->assertException(
            MasterDataException::item('item_potion'),
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            'Master item data not found: item_potion'
        );
    }

    #[Test]
    public function 装備マスターの不足を表せる(): void
    {
        $this->assertException(
            MasterDataException::equipment('equipment_001'),
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            'Master equipment data not found: equipment_001'
        );
    }

    #[Test]
    public function ユニットレベルマスターの不足はレアリティとレベルで表す(): void
    {
        // レベルマスターはIDを持たず (レアリティ, レベル) で一意
        $this->assertException(
            MasterDataException::unitLevel('SSR', 80),
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            'Master unit level data not found: rarity=SSR, level=80'
        );
    }

    #[Test]
    public function プレイヤーレベルマスターの不足を表せる(): void
    {
        $this->assertException(
            MasterDataException::playerLevel(120),
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            'Master player level data not found: level=120'
        );
    }

    #[Test]
    public function 商品マスターの不足は商品専用のエラーコードになる(): void
    {
        // ストア側の商品が引けないのはマスター不整合と原因が違うため、
        // クライアントが出し分けられるようコードを分けている
        $this->assertException(
            MasterDataException::product('product_001'),
            GameErrorCode::PRODUCT_NOT_FOUND,
            'Master product data not found: product_001'
        );
    }

    #[Test]
    public function 課金商品マスターの不足も商品専用のエラーコードになる(): void
    {
        $this->assertException(
            MasterDataException::inAppPurchase('diamond_pack_001'),
            GameErrorCode::PRODUCT_NOT_FOUND,
            'Master in-app purchase data not found: diamond_pack_001'
        );
    }

    #[Test]
    public function 汎用のファクトリは種別とidを載せる(): void
    {
        // 専用のファクトリが無いマスター向けの受け皿
        $this->assertException(
            MasterDataException::generic('gacha', 'gacha_001'),
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            'Master data not found: gacha (ID: gacha_001)'
        );
    }

    #[Test]
    public function 汎用のファクトリは数値のidも受ける(): void
    {
        $this->assertStringContainsString(
            'ID: 42',
            MasterDataException::generic('vip_level', 42)->getMessage()
        );
    }

    #[Test]
    public function idがメッセージに載る(): void
    {
        // どのIDが無いのか分からないと、マスターのどこを直せばいいか特定できない
        foreach (['unit', 'item', 'equipment', 'product', 'inAppPurchase'] as $kind) {
            $message = MasterDataException::{$kind}('missing_id_001')->getMessage();

            $this->assertStringContainsString('missing_id_001', $message, "{$kind} にIDが載っていない");
        }
    }

    #[Test]
    public function ゲーム例外として扱える(): void
    {
        // コントローラの handleException が GameException として拾えること
        $this->assertInstanceOf(GameException::class, MasterDataException::unit('unit_001'));
    }

    private function assertException(
        MasterDataException $exception,
        int $expectedErrorCode,
        string $expectedMessage,
    ): void {
        $this->assertSame($expectedErrorCode, $exception->getErrorCode());
        $this->assertSame($expectedMessage, $exception->getMessage());
    }
}
