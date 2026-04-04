<?php

namespace Tests\Unit\Models\Trx;

use App\Models\Trx\TrxItem;
use App\Persistence\ApiSession;
use App\Utilities\Clock;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class BaseTrxTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ApiSessionを初期化
        Clock::initialize();
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
            'amount' => 100,
        ]);

        // Act: amountを変更
        $item->setAmount(90);

        // Assert: 相対的な変更が記録されている
        $this->assertTrue($item->hasRelativeChanges());
        $relativeChanges = $item->getRelativeChanges();
        $this->assertEquals(-10, $relativeChanges['amount']);
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
            'amount' => 100,
        ]);

        // Act: 複数回amountを変更
        $item->setAmount($item->amount - 10); // 100 -> 90, diff = -10
        $item->setAmount($item->amount - 5);  // 90 -> 85, diff = -5
        $item->setAmount($item->amount + 15); // 85 -> 100, diff = +15

        // Assert: 相対的な変更が累積されている
        $this->assertTrue($item->hasRelativeChanges());
        $relativeChanges = $item->getRelativeChanges();
        // -10 + (-5) + 15 = 0
        $this->assertEquals(0, $relativeChanges['amount']);
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
            'amount' => 100,
        ]);
        $item->exists = false;

        // Act: amountを変更
        $item->setAmount(90);

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
            'amount' => 100,
        ]);

        // Act: 相対的な変更を記録してクリア
        $item->setAmount(90);
        $this->assertTrue($item->hasRelativeChanges());
        
        $item->clearRelativeChanges();

        // Assert: 相対的な変更がクリアされた
        $this->assertFalse($item->hasRelativeChanges());
    }
}
