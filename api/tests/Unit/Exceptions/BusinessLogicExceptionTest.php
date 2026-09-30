<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BusinessLogicException のテスト
 *
 * 業務ルールに反したときの例外。エラーコードはクライアントが
 * 分岐に使う値なので、ファクトリごとに固定する。
 * メッセージには不足量など原因の特定に要る情報が入る。
 */
class BusinessLogicExceptionTest extends TestCase
{
    #[Test]
    public function 所持数の不足は必要量と現在値をメッセージに含む(): void
    {
        $stamina = BusinessLogicException::staminaNotEnough(10, 3);
        $this->assertSame(GameErrorCode::STAMINA_NOT_ENOUGH, $stamina->getErrorCode());
        $this->assertSame('Stamina not enough. Required: 10, Current: 3', $stamina->getMessage());

        $diamond = BusinessLogicException::diamondNotEnough(100, 20);
        $this->assertSame(GameErrorCode::DIAMOND_NOT_ENOUGH, $diamond->getErrorCode());
        $this->assertSame('Diamond not enough. Required: 100, Current: 20', $diamond->getMessage());

        $item = BusinessLogicException::itemNotEnough('item_potion', 5, 1);
        $this->assertSame(GameErrorCode::ITEM_NOT_ENOUGH, $item->getErrorCode());
        $this->assertSame('Item not enough: item_potion. Required: 5, Current: 1', $item->getMessage());

        $currency = BusinessLogicException::insufficientCurrency('gold', 500, 100);
        $this->assertSame(GameErrorCode::INSUFFICIENT_CURRENCY, $currency->getErrorCode());
        $this->assertSame('Insufficient currency: gold. Required: 500, Current: 100', $currency->getMessage());
    }

    #[Test]
    public function アイテムの種別違いは期待値と実際値を並べる(): void
    {
        $exception = BusinessLogicException::invalidItemType('item_001', 'UnitExp', 'EquipmentExp');

        $this->assertSame(GameErrorCode::INVALID_ITEM_TYPE, $exception->getErrorCode());
        $this->assertSame(
            'Invalid item type: item_001. Expected: UnitExp, Actual: EquipmentExp',
            $exception->getMessage()
        );
    }

    #[Test]
    public function ユニットの最大レベル到達(): void
    {
        $exception = BusinessLogicException::unitMaxLevelReached(7, 100);

        $this->assertSame(GameErrorCode::UNIT_MAX_LEVEL_REACHED, $exception->getErrorCode());
        $this->assertStringContainsString('7', $exception->getMessage());
        $this->assertStringContainsString('100', $exception->getMessage());
    }

    #[Test]
    public function 課金まわりのファクトリ(): void
    {
        $limit = BusinessLogicException::purchaseLimitExceeded('pack_001', 3);
        $this->assertSame(GameErrorCode::PURCHASE_LIMIT_EXCEEDED, $limit->getErrorCode());
        $this->assertSame('Purchase limit exceeded for product: pack_001 (Limit: 3)', $limit->getMessage());

        $inactive = BusinessLogicException::productInactive('pack_001');
        $this->assertSame(GameErrorCode::PRODUCT_INACTIVE, $inactive->getErrorCode());
        $this->assertSame('Product is inactive: pack_001', $inactive->getMessage());

        $productType = BusinessLogicException::invalidProductType('Subscription');
        $this->assertSame(GameErrorCode::INVALID_PRODUCT_TYPE, $productType->getErrorCode());
        $this->assertSame('Invalid product type: Subscription', $productType->getMessage());
    }

    #[Test]
    public function リソース種別が不正(): void
    {
        $exception = BusinessLogicException::invalidResourceType('no_such_type');

        $this->assertSame(GameErrorCode::INVALID_RESOURCE_TYPE, $exception->getErrorCode());
        $this->assertSame('Invalid resource type: no_such_type', $exception->getMessage());
    }

    #[Test]
    public function 既存デバイスでのサインアップは_sign_inへ誘導する(): void
    {
        $exception = BusinessLogicException::deviceAlreadyExists('device-uuid-1');

        $this->assertSame(GameErrorCode::DEVICE_ALREADY_EXISTS, $exception->getErrorCode());
        $this->assertStringContainsString('sign_in', $exception->getMessage());
    }

    #[Test]
    public function game_exceptionとして扱える(): void
    {
        $exception = BusinessLogicException::staminaNotEnough(1, 0);

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertSame(GameErrorCode::STAMINA_NOT_ENOUGH, $exception->toArray()['error_code']);
    }
}
