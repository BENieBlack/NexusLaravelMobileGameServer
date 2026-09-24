<?php

namespace Tests\Unit\Adapters\Gacha;

use App\Adapters\Gacha\GachaProgressAdapter;
use App\Models\Trx\TrxGacha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GachaProgressAdapter の Model→DTO 変換テスト
 *
 * 日次カウンタと累計カウンタが同じ形をしているため、
 * 取り違えていないことを別々の値で検証する。
 */
class GachaProgressAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $dto = GachaProgressAdapter::toDto($this->makeProgress());

        $this->assertSame(501, $dto->getSysPlayerId());
        $this->assertSame('gacha_pickup_001', $dto->getMstGachaId());
        $this->assertSame(3, $dto->getCurrentStep());
    }

    #[Test]
    public function test_daily_and_total_counters_are_not_swapped(): void
    {
        $dto = GachaProgressAdapter::toDto($this->makeProgress([
            'daily_draw_count' => 7,
            'daily_reset_at' => '2026-01-02 04:00:00',
            'total_draw_count' => 120,
            'total_reset_at' => '2026-02-01 04:00:00',
        ]));

        $this->assertSame(7, $dto->getDailyDrawCount());
        $this->assertSame('2026-01-02 04:00:00', $dto->getDailyResetAt());
        $this->assertSame(120, $dto->getTotalDrawCount());
        $this->assertSame('2026-02-01 04:00:00', $dto->getTotalResetAt());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = GachaProgressAdapter::toDtoArray([
            $this->makeProgress(['mst_gacha_id' => 'gacha_a']),
            $this->makeProgress(['mst_gacha_id' => 'gacha_b']),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame(['gacha_a', 'gacha_b'], array_map(fn ($dto) => $dto->getMstGachaId(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], GachaProgressAdapter::toDtoArray([]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProgress(array $overrides = []): TrxGacha
    {
        $model = new TrxGacha;
        $model->forceFill(array_merge([
            'sys_player_id' => 501,
            'mst_gacha_id' => 'gacha_pickup_001',
            'current_step' => 3,
            'daily_draw_count' => 1,
            'daily_reset_at' => '2026-01-02 04:00:00',
            'total_draw_count' => 10,
            'total_reset_at' => '2026-02-01 04:00:00',
        ], $overrides));

        return $model;
    }
}
