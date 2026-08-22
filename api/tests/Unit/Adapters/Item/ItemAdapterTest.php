<?php

namespace Tests\Unit\Adapters\Item;

use App\Adapters\Item\ItemAdapter;
use App\Models\Trx\TrxItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ItemAdapter の Model→DTO 変換テスト
 *
 * 無償/有償の所持数を取り違えていないことを検証する。
 */
class ItemAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $dto = ItemAdapter::toDto($this->makeItem());

        $this->assertSame(501, $dto->getSysPlayerId());
        $this->assertSame('item_potion_001', $dto->getMstItemId());
    }

    #[Test]
    public function test_free_and_paid_amounts_are_not_swapped(): void
    {
        $dto = ItemAdapter::toDto($this->makeItem([
            'free_amount' => 3,
            'paid_amount' => 8,
        ]));

        $this->assertSame(3, $dto->getFreeAmount());
        $this->assertSame(8, $dto->getPaidAmount());
        $this->assertSame(11, $dto->getTotalAmount());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = ItemAdapter::toDtoArray([
            $this->makeItem(['mst_item_id' => 'item_a']),
            $this->makeItem(['mst_item_id' => 'item_b']),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame(['item_a', 'item_b'], array_map(fn ($dto) => $dto->getMstItemId(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], ItemAdapter::toDtoArray([]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeItem(array $overrides = []): TrxItem
    {
        $model = new TrxItem;
        $model->forceFill(array_merge([
            'sys_player_id' => 501,
            'mst_item_id' => 'item_potion_001',
            'free_amount' => 1,
            'paid_amount' => 2,
        ], $overrides));

        return $model;
    }
}
