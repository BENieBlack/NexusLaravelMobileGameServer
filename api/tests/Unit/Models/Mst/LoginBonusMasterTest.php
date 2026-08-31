<?php

namespace Tests\Unit\Models\Mst;

use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ログインボーナスマスターのテスト
 *
 * 種別（日次／復帰）の判定と、期間限定ボーナスの有効期間。
 * 配布量は content_quantity × amount で、片方だけ見ると数が合わない。
 */
class LoginBonusMasterTest extends TestCase
{
    // ========================================
    // 種別
    // ========================================

    #[Test]
    public function 種別を判定できる(): void
    {
        // 判定が重なると復帰用の設定が日次として配られる
        $daily = $this->makeBonus(MstLoginBonus::TYPE_DAILY);
        $comeback = $this->makeBonus(MstLoginBonus::TYPE_COMEBACK);

        $this->assertTrue($daily->isDailyType());
        $this->assertFalse($daily->isComebackType());

        $this->assertTrue($comeback->isComebackType());
        $this->assertFalse($comeback->isDailyType());
    }

    #[Test]
    public function 復帰用の項目は日次では未設定でよい(): void
    {
        $daily = $this->makeBonus(MstLoginBonus::TYPE_DAILY);

        $this->assertNull($daily->getRequiredAbsentDays());
        $this->assertNull($daily->getValidDays());
        $this->assertSame(0, $daily->getPriority(), '優先度の既定は0');
    }

    #[Test]
    public function 復帰用の項目を読み出せる(): void
    {
        $comeback = $this->makeBonus(MstLoginBonus::TYPE_COMEBACK, [
            'required_absent_days' => 7,
            'valid_days' => 30,
            'priority' => 10,
        ]);

        $this->assertSame(7, $comeback->getRequiredAbsentDays());
        $this->assertSame(30, $comeback->getValidDays());
        $this->assertSame(10, $comeback->getPriority());
    }

    // ========================================
    // 期間限定
    // ========================================

    #[Test]
    public function 期間の指定が無ければ常に有効(): void
    {
        $bonus = $this->makeBonus(MstLoginBonus::TYPE_DAILY);

        $this->assertTrue($bonus->isWithinPeriod(new \DateTimeImmutable('2026-03-15 12:00:00')));
    }

    #[Test]
    public function 開始前は無効(): void
    {
        $bonus = $this->makeBonus(MstLoginBonus::TYPE_DAILY, [
            'start_at' => new \DateTimeImmutable('2026-03-16 00:00:00'),
        ]);

        $this->assertFalse($bonus->isWithinPeriod(new \DateTimeImmutable('2026-03-15 12:00:00')));
    }

    #[Test]
    public function 終了後は無効(): void
    {
        $bonus = $this->makeBonus(MstLoginBonus::TYPE_DAILY, [
            'end_at' => new \DateTimeImmutable('2026-03-15 11:59:59'),
        ]);

        $this->assertFalse($bonus->isWithinPeriod(new \DateTimeImmutable('2026-03-15 12:00:00')));
    }

    #[Test]
    public function 期間内は有効(): void
    {
        $bonus = $this->makeBonus(MstLoginBonus::TYPE_DAILY, [
            'start_at' => new \DateTimeImmutable('2026-03-01 00:00:00'),
            'end_at' => new \DateTimeImmutable('2026-03-31 23:59:59'),
        ]);

        $this->assertTrue($bonus->isWithinPeriod(new \DateTimeImmutable('2026-03-15 12:00:00')));
    }

    #[Test]
    public function 開始と終了の境界は有効に含む(): void
    {
        $bonus = $this->makeBonus(MstLoginBonus::TYPE_DAILY, [
            'start_at' => new \DateTimeImmutable('2026-03-01 00:00:00'),
            'end_at' => new \DateTimeImmutable('2026-03-31 23:59:59'),
        ]);

        $this->assertTrue($bonus->isWithinPeriod(new \DateTimeImmutable('2026-03-01 00:00:00')));
        $this->assertTrue($bonus->isWithinPeriod(new \DateTimeImmutable('2026-03-31 23:59:59')));
    }

    // ========================================
    // 配布内容
    // ========================================

    #[Test]
    public function 配布内容を読み出せる(): void
    {
        $content = $this->makeContent(['content_option' => ['grade' => 1]]);

        $this->assertSame('item', $content->getContentType());
        $this->assertSame('item_potion', $content->getContentMstId());
        $this->assertSame(['grade' => 1], $content->getContentOption());
        $this->assertSame(10, $content->getContentQuantity());
        $this->assertSame(3, $content->getAmount());
        $this->assertFalse($content->getIsPaid());
    }

    #[Test]
    public function 配布量は基本個数と配布回数の積になる(): void
    {
        $this->assertSame(30, $this->makeContent()->getTotalQuantity());
        $this->assertSame(1, $this->makeContent(['content_quantity' => 1, 'amount' => 1])->getTotalQuantity());
    }

    #[Test]
    public function オプションは未設定でもよい(): void
    {
        $this->assertNull($this->makeContent()->getContentOption());
    }

    #[Test]
    public function 有償の配布を表せる(): void
    {
        // ダイヤは有償・無償で残高の枠が分かれる
        $this->assertTrue($this->makeContent(['content_type' => 'diamond', 'is_paid' => true])->getIsPaid());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBonus(string $type, array $attributes = []): MstLoginBonus
    {
        $bonus = new MstLoginBonus(array_merge([
            'id' => 'bonus_001',
            'type' => $type,
            'day' => 1,
            'loop_days' => 7,
            'priority' => 0,
            'is_active' => true,
        ], $attributes));

        // start_at / end_at は fillable 外のため直接入れる
        foreach (['start_at', 'end_at'] as $key) {
            if (isset($attributes[$key])) {
                $bonus->setAttribute($key, $attributes[$key]);
            }
        }

        return $bonus;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeContent(array $attributes = []): MstLoginBonusContent
    {
        return new MstLoginBonusContent(array_merge([
            'mst_login_bonus_id' => 'bonus_001',
            'content_type' => 'item',
            'content_mst_id' => 'item_potion',
            'content_quantity' => 10,
            'amount' => 3,
            'is_paid' => false,
            'sort_order' => 1,
        ], $attributes));
    }
}
