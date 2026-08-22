<?php

namespace Tests\Unit\Adapters\Player;

use App\Adapters\Player\PlayerVipAdapter;
use App\Models\Sys\SysPlayer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PlayerVipAdapter の Model→DTO 変換テスト
 *
 * total_paid_amount は decimal:2 キャストで文字列として返るため、
 * float を要求するDTOへ渡したときに壊れないことを検証する。
 */
class PlayerVipAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $dto = PlayerVipAdapter::toDto($this->makePlayer());

        $this->assertSame(501, $dto->getSysPlayerId());
        $this->assertSame(2500, $dto->getVipPoint());
    }

    #[Test]
    public function test_total_paid_amount_is_converted_to_float(): void
    {
        $dto = PlayerVipAdapter::toDto($this->makePlayer(['total_paid_amount' => 1234.56]));

        $this->assertIsFloat($dto->getTotalPaidAmount());
        $this->assertSame(1234.56, $dto->getTotalPaidAmount());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = PlayerVipAdapter::toDtoArray([
            $this->makePlayer(['id' => 1]),
            $this->makePlayer(['id' => 2]),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame([1, 2], array_map(fn ($dto) => $dto->getSysPlayerId(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], PlayerVipAdapter::toDtoArray([]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePlayer(array $overrides = []): SysPlayer
    {
        $model = new SysPlayer;
        $model->forceFill(array_merge([
            'id' => 501,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'my_id' => 'MYID0001',
            'name' => 'テストプレイヤー',
            'level' => 12,
            'level_exp' => 3400,
            'vip_point' => 2500,
            'total_paid_amount' => 980.00,
        ], $overrides));

        return $model;
    }
}
