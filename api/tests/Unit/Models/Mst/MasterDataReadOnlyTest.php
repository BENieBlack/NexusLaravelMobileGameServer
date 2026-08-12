<?php

namespace Tests\Unit\Models\Mst;

use App\Models\Mst\MstItem;
use Nexus\Core\Exceptions\MasterDataReadOnlyException;
use Nexus\Core\Models\Mst\_BaseMst;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * マスターデータの読み取り専用ガードのテスト
 *
 * マスターデータはデプロイで投入するものであり、実行時に書き換えてはならない。
 * TestCaseは組み立てのため書き込みを許可しているので、
 * このテストでは明示的に禁止状態に戻して検証する。
 */
class MasterDataReadOnlyTest extends TestCase
{
    use RefreshMultipleDatabases;

    /**
     * mst_itemの必須カラムを満たす属性を組み立てる
     *
     * @return array<string, mixed>
     */
    private function itemAttributes(string $id): array
    {
        return [
            'id' => $id,
            'deploy_key' => 202601010,
            'type' => 'consumable',
            'effect' => 'heal_hp',
            'value' => 50.0,
        ];
    }

    protected function tearDown(): void
    {
        // 他のテストに影響しないよう許可状態へ戻す
        _BaseMst::allowWrites();

        parent::tearDown();
    }

    #[Test]
    public function test_create_is_rejected_when_writes_are_not_allowed(): void
    {
        _BaseMst::disallowWrites();

        $this->expectException(MasterDataReadOnlyException::class);

        MstItem::create($this->itemAttributes('item_guard_test'));
    }

    #[Test]
    public function test_update_is_rejected_when_writes_are_not_allowed(): void
    {
        $item = _BaseMst::allowWrites(fn () => MstItem::create($this->itemAttributes('item_guard_update')));

        _BaseMst::disallowWrites();

        $this->expectException(MasterDataReadOnlyException::class);

        $item->update(['value' => 999.0]);
    }

    #[Test]
    public function test_delete_is_rejected_when_writes_are_not_allowed(): void
    {
        $item = _BaseMst::allowWrites(fn () => MstItem::create($this->itemAttributes('item_guard_delete')));

        _BaseMst::disallowWrites();

        $this->expectException(MasterDataReadOnlyException::class);

        $item->delete();
    }

    #[Test]
    public function test_reading_is_always_allowed(): void
    {
        _BaseMst::allowWrites(fn () => MstItem::create($this->itemAttributes('item_guard_read')));

        _BaseMst::disallowWrites();

        // 読み取りはガードの対象外
        $found = MstItem::query()->where('id', 'item_guard_read')->first();

        $this->assertNotNull($found);
        $this->assertSame('consumable', $found->type);
    }

    #[Test]
    public function test_allow_writes_with_callback_restores_previous_state(): void
    {
        _BaseMst::disallowWrites();

        _BaseMst::allowWrites(function () {
            $this->assertTrue(_BaseMst::writesAllowed(), 'コールバック内では許可されるべき');
        });

        $this->assertFalse(_BaseMst::writesAllowed(), 'コールバック後は元の状態に戻るべき');
    }
}
