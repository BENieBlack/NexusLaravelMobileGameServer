<?php

namespace Nexus\Core\Tests\Unit\UnitOfWork;

use Nexus\Core\Models\Trx\_BaseTrx;
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

    public function test_models_without_primary_key_are_told_apart_by_instance(): void
    {
        $updated = new UpdatedResources;
        $updated->record(new FakeUnit(['level' => 1]));
        $updated->record(new FakeUnit(['level' => 2]));

        $this->assertCount(2, $updated->getByTable('fake_unit'));
    }
}

class FakeUnit extends _BaseTrx
{
    protected $table = 'fake_unit';

    protected $guarded = [];

    public function toResponseArray(): array
    {
        return $this->getAttributes();
    }
}

class FakeItem extends _BaseTrx
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
