<?php

namespace Tests\Unit\Models\Trx;

use App\Models\Trx\TrxItem;
use App\Persistence\ApiSession;
use Nexus\Core\Utilities\ClockUtility;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class BaseTrxTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        // ApiSessionを初期化
        ClockUtility::initialize();
        ApiSession::setSysPlayerId(1);
    }

    /**
     * 相対的な変更が正しく記録されることをテスト
     */
    public function test_relative_changes_are_recorded_correctly(): void
    {
        // Arrange: 既存のアイテムを作成
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'test_item',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);

        // Act: paid_amountを変更
        $item->setPaidAmount(10);

        // Assert: 相対的な変更が記録されている
        $this->assertTrue($item->hasRelativeChanges());
        $relativeChanges = $item->getRelativeChanges();
        $this->assertEquals(-10, $relativeChanges['paid_amount']);
    }

    /**
     * 複数回の相対的な変更が累積されることをテスト
     */
    public function test_multiple_relative_changes_accumulate(): void
    {
        // Arrange: 既存のアイテムを作成
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'test_item_2',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);

        // Act: 複数回paid_amountを変更
        $item->setPaidAmount($item->paid_amount - 10); // 20 -> 10, diff = -10
        $item->setPaidAmount($item->paid_amount - 5);  // 10 -> 5, diff = -5
        $item->setPaidAmount($item->paid_amount + 15); // 5 -> 20, diff = +15

        // Assert: 相対的な変更が累積されている
        $this->assertTrue($item->hasRelativeChanges());
        $relativeChanges = $item->getRelativeChanges();
        // -10 + (-5) + 15 = 0
        $this->assertEquals(0, $relativeChanges['paid_amount']);
    }

    /**
     * 新規モデルでは相対的な変更が記録されないことをテスト
     */
    public function test_new_model_does_not_record_relative_changes(): void
    {
        // Arrange: 新規アイテムを作成（DBに保存しない）
        $item = new TrxItem([
            'sys_player_id' => 1,
            'mst_item_id' => 'test_item_3',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);
        $item->exists = false;

        // Act: paid_amountを変更
        $item->setPaidAmount(10);

        // Assert: 相対的な変更は記録されない
        $this->assertFalse($item->hasRelativeChanges());
    }

    /**
     * 相対的な変更がクリアされることをテスト
     */
    public function test_relative_changes_can_be_cleared(): void
    {
        // Arrange: 既存のアイテムを作成
        $item = TrxItem::create([
            'sys_player_id' => 1,
            'mst_item_id' => 'test_item_4',
            'free_amount' => 80,
            'paid_amount' => 20,
        ]);

        // Act: 相対的な変更を記録してクリア
        $item->setPaidAmount(10);
        $this->assertTrue($item->hasRelativeChanges());

        $item->clearRelativeChanges();

        // Assert: 相対的な変更がクリアされた
        $this->assertFalse($item->hasRelativeChanges());
    }
}
