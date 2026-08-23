<?php

namespace NexusBilling\Tests\Unit\Validators;

use Nexus\Core\Utilities\ClockUtility;
use NexusBilling\Constants\PurchaseLimitResetType;
use NexusBilling\Validators\_BasePurchaseLimitValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * _BasePurchaseLimitValidator のユニットテスト
 *
 * 購入回数の上限判定と、日次/週次/月次のリセット判定を検証する。
 * 実課金の回数制限を決める箇所なので、リセットの境界を明示的に固定して確認する。
 */
class BasePurchaseLimitValidatorTest extends TestCase
{
    private _BasePurchaseLimitValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new _BasePurchaseLimitValidator;

        // ゲーム内日付の境界は既定の0時に固定する（env()に依存させない）
        ClockUtility::setDayStartTime('00:00:00');
        ClockUtility::setNow('2026-03-15 12:00:00');
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 購入制限がnullなら何回買っても超過しない(): void
    {
        $this->assertFalse($this->validator->isLimitExceeded(
            null,
            9999,
            PurchaseLimitResetType::NONE,
            null
        ));
    }

    #[Test]
    public function 購入回数が制限未満なら超過しない(): void
    {
        $this->assertFalse($this->validator->isLimitExceeded(
            3,
            2,
            PurchaseLimitResetType::NONE,
            '2026-03-15 09:00:00'
        ));
    }

    #[Test]
    public function 購入回数が制限に達したら超過とみなす(): void
    {
        $this->assertTrue($this->validator->isLimitExceeded(
            3,
            3,
            PurchaseLimitResetType::NONE,
            '2026-03-15 09:00:00'
        ));
    }

    #[Test]
    public function リセットなしは日付が変わっても持ち越す(): void
    {
        $this->assertTrue($this->validator->isLimitExceeded(
            1,
            1,
            PurchaseLimitResetType::NONE,
            '2020-01-01 00:00:00'
        ));
    }

    #[Test]
    public function 初回購入はリセット日時がないためリセット不要(): void
    {
        $this->assertFalse($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::DAILY,
            null
        ));
    }

    #[Test]
    public function 未知のリセット種別はリセットしない(): void
    {
        $this->assertFalse($this->validator->shouldResetPurchaseCount(
            'Yearly',
            '2020-01-01 00:00:00'
        ));
    }

    #[Test]
    public function 日次は前日のリセット日時ならリセットする(): void
    {
        $this->assertTrue($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::DAILY,
            '2026-03-14 23:59:59'
        ));

        // リセットされるのでカウントは0扱いになり、上限に達していても買える
        $this->assertFalse($this->validator->isLimitExceeded(
            1,
            1,
            PurchaseLimitResetType::DAILY,
            '2026-03-14 23:59:59'
        ));
    }

    #[Test]
    public function 日次は同日のリセット日時ならリセットしない(): void
    {
        $this->assertFalse($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::DAILY,
            '2026-03-15 00:00:00'
        ));

        $this->assertTrue($this->validator->isLimitExceeded(
            1,
            1,
            PurchaseLimitResetType::DAILY,
            '2026-03-15 00:00:00'
        ));
    }

    #[Test]
    public function 日次の境界はday_start_timeに従う(): void
    {
        // 日付の切り替わりを9時にすると、同じ暦日でも9時前は「前日」になる
        ClockUtility::setDayStartTime('09:00:00');
        ClockUtility::setNow('2026-03-15 10:00:00');

        $this->assertTrue($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::DAILY,
            '2026-03-15 08:00:00'
        ));
        $this->assertFalse($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::DAILY,
            '2026-03-15 09:00:00'
        ));
    }

    #[Test]
    public function 週次は別の週ならリセットする(): void
    {
        // 2026-03-15は日曜（ISO週11の最終日）、2026-03-16は月曜で週が変わる
        ClockUtility::setNow('2026-03-16 00:00:00');

        $this->assertTrue($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::WEEKLY,
            '2026-03-15 23:59:59'
        ));
    }

    #[Test]
    public function 週次は同じ週ならリセットしない(): void
    {
        $this->assertFalse($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::WEEKLY,
            '2026-03-09 00:00:00'
        ));
    }

    #[Test]
    public function 週次は年をまたいでも同じ週ならリセットしない(): void
    {
        // 2025-12-29(月)〜2026-01-04(日) は同じISO週（2026-W01）。
        // 暦年で比較すると週が変わったと誤判定し、上限が余分にリセットされる
        ClockUtility::setNow('2026-01-01 12:00:00');

        $this->assertFalse($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::WEEKLY,
            '2025-12-29 12:00:00'
        ));
    }

    #[Test]
    public function 週次は同じ週番号でも別の年ならリセットする(): void
    {
        // 2026-W11 と 2025-W11 は同じ週番号だが別の週
        $this->assertTrue($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::WEEKLY,
            '2025-03-12 12:00:00'
        ));
    }

    #[Test]
    public function 月次は別の月ならリセットする(): void
    {
        $this->assertTrue($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::MONTHLY,
            '2026-02-28 23:59:59'
        ));
    }

    #[Test]
    public function 月次は同じ月ならリセットしない(): void
    {
        $this->assertFalse($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::MONTHLY,
            '2026-03-01 00:00:00'
        ));
    }

    #[Test]
    public function 月次は同じ月でも別の年ならリセットする(): void
    {
        $this->assertTrue($this->validator->shouldResetPurchaseCount(
            PurchaseLimitResetType::MONTHLY,
            '2025-03-15 12:00:00'
        ));
    }

    #[Test]
    public function 有効な購入回数はリセット時のみ0になる(): void
    {
        $this->assertSame(0, $this->validator->calculateEffectiveCount(
            5,
            PurchaseLimitResetType::DAILY,
            '2026-03-14 12:00:00'
        ));
        $this->assertSame(5, $this->validator->calculateEffectiveCount(
            5,
            PurchaseLimitResetType::DAILY,
            '2026-03-15 00:00:00'
        ));
    }

    #[Test]
    public function リセットが必要なときだけ新しいリセット日時を返す(): void
    {
        $this->assertSame('2026-03-15 12:00:00', $this->validator->getNewResetDateIfNeeded(
            PurchaseLimitResetType::DAILY,
            '2026-03-14 12:00:00'
        ));
        $this->assertNull($this->validator->getNewResetDateIfNeeded(
            PurchaseLimitResetType::DAILY,
            '2026-03-15 00:00:00'
        ));
    }
}
