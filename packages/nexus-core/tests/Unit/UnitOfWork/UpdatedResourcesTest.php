<?php

namespace Nexus\Core\Tests\Unit\UnitOfWork;

use Nexus\Core\Models\Trx\_BaseTrx;
use Nexus\Core\UnitOfWork\Contracts\ReturnedAsSingleUpdatedResource;
use Nexus\Core\UnitOfWork\Contracts\ReturnedAsUpdatedResource;
use Nexus\Core\UnitOfWork\UpdatedResources;
use PHPUnit\Framework\TestCase;

class UpdatedResourcesTest extends TestCase
{
    public function test_starts_empty(): void
    {
        $updated = new UpdatedResources;

        $this->assertTrue($updated->isEmpty());
        $this->assertSame([], $updated->getTables());
        $this->assertSame([], $updated->toResponseArray());
    }

    public function test_groups_models_by_table_with_list_suffix(): void
    {
        $updated = new UpdatedResources;
        $updated->record(new FakeUnit(['id' => 1, 'level' => 5]));
        $updated->record(new FakeUnit(['id' => 2, 'level' => 3]));
        $updated->record(new FakeItem(['mst_item_id' => 'potion', 'amount' => 9]));

        $this->assertFalse($updated->isEmpty());
        $this->assertSame(['fake_unit', 'fake_item'], $updated->getTables());
        $this->assertCount(2, $updated->getByTable('fake_unit'));

        $response = $updated->toResponseArray();
        $this->assertSame(['fake_unit_list', 'fake_item_list'], array_keys($response));
        $this->assertCount(2, $response['fake_unit_list']);
        $this->assertCount(1, $response['fake_item_list']);
    }

    public function test_single_row_table_is_returned_as_an_object_without_list_suffix(): void
    {
        $updated = new UpdatedResources;
        $updated->record(new FakeSingle(['id' => 1, 'level' => 5]));
        $updated->record(new FakeSingle(['id' => 1, 'level' => 6]));
        $updated->record(new FakeItem(['mst_item_id' => 'potion', 'amount' => 9]));

        $response = $updated->toResponseArray();

        $this->assertSame(['fake_single', 'fake_item_list'], array_keys($response));
        // 配列ではなくオブジェクト1件。同じ行は最後の状態
        $this->assertSame(['id' => 1, 'level' => 6], $response['fake_single']);
    }

    public function test_same_row_recorded_twice_keeps_the_latest_state(): void
    {
        $updated = new UpdatedResources;
        $updated->record(new FakeUnit(['id' => 1, 'level' => 5]));
        $updated->record(new FakeUnit(['id' => 1, 'level' => 8]));

        $units = $updated->getByTable('fake_unit');
        $this->assertCount(1, $units);
        $this->assertSame(8, $units[0]->getAttribute('level'));
    }

    public function test_composite_primary_key_distinguishes_rows(): void
    {
        $updated = new UpdatedResources;
        $updated->record(new FakeItem(['sys_player_id' => 1, 'mst_item_id' => 'potion', 'amount' => 1]));
        $updated->record(new FakeItem(['sys_player_id' => 1, 'mst_item_id' => 'elixir', 'amount' => 1]));
        $updated->record(new FakeItem(['sys_player_id' => 1, 'mst_item_id' => 'potion', 'amount' => 2]));

        $this->assertCount(2, $updated->getByTable('fake_item'));
    }

    public function test_models_not_marked_as_returned_are_not_recorded(): void
    {
        $updated = new UpdatedResources;
        $updated->record(new FakeInternal(['id' => 1]));

        $this->assertTrue($updated->isEmpty());
    }

    public function test_record_operations_takes_inserts_and_updates_but_not_deletes(): void
    {
        $inserted = new FakeUnit(['id' => 1, 'level' => 1]);
        $updatedModel = new FakeItem(['sys_player_id' => 1, 'mst_item_id' => 'potion', 'amount' => 3]);
        $deleted = new FakeUnit(['id' => 9, 'level' => 9]);

        $updated = new UpdatedResources;
        $updated->recordOperations([
            'inserts' => ['trx1.fake_unit' => ['models' => [$inserted, new FakeInternal(['id' => 5])]]],
            'updates' => [['model' => $updatedModel], ['model' => new FakeInternal(['id' => 6])]],
            'deletes' => [['model' => $deleted]],
        ]);

        $this->assertSame(['fake_unit', 'fake_item'], $updated->getTables());
        $this->assertCount(1, $updated->getByTable('fake_unit'));
        $this->assertSame(1, $updated->getByTable('fake_unit')[0]->getAttribute('id'));
        $this->assertCount(1, $updated->getByTable('fake_item'));
    }

    public function test_models_without_primary_key_are_told_apart_by_instance(): void
    {
        $updated = new UpdatedResources;
        $updated->record(new FakeUnit(['level' => 1]));
        $updated->record(new FakeUnit(['level' => 2]));

        $this->assertCount(2, $updated->getByTable('fake_unit'));
    }
}

class FakeUnit extends _BaseTrx implements ReturnedAsUpdatedResource
{
    protected $table = 'fake_unit';

    protected $guarded = [];

    public function toResponseArray(): array
    {
        return $this->getAttributes();
    }
}

class FakeItem extends _BaseTrx implements ReturnedAsUpdatedResource
{
    protected $table = 'fake_item';

    protected $guarded = [];

    protected $primaryKey = ['sys_player_id', 'mst_item_id'];

    public $incrementing = false;

    public function toResponseArray(): array
    {
        return $this->getAttributes();
    }
}

/**
 * ReturnedAsUpdatedResource を実装していない（ログ・内訳・履歴のような内部用）モデル
 */
class FakeInternal extends _BaseTrx
{
    protected $table = 'fake_internal';

    protected $guarded = [];

    public function toResponseArray(): array
    {
        return $this->getAttributes();
    }
}

/**
 * プレイヤーに1行しかないテーブル
 */
class FakeSingle extends _BaseTrx implements ReturnedAsSingleUpdatedResource
{
    protected $table = 'fake_single';

    protected $guarded = [];

    public function toResponseArray(): array
    {
        return $this->getAttributes();
    }
}
