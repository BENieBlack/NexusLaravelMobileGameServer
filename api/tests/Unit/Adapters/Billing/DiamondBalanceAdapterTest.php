<?php

namespace Tests\Unit\Adapters\Billing;

use App\Adapters\Billing\DiamondBalanceAdapter;
use App\Models\Trx\TrxDiamond;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DiamondBalanceAdapter の Model→DTO 変換テスト
 *
 * 有償の残高はプラットフォームごとに分かれて持つため、
 * プラットフォームと有償残高の取りこぼしがないことを検証する。
 * 無償ダイヤは trx_item の free_diamond にあるので、DTO に無償の項目は無い。
 */
class DiamondBalanceAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $dto = DiamondBalanceAdapter::toDto($this->makeDiamond());

        $this->assertSame(501, $dto->getSysPlayerId());
        $this->assertSame('Apple', $dto->getPlatform());
    }

    #[Test]
    public function test_converts_paid_amount_only(): void
    {
        $dto = DiamondBalanceAdapter::toDto($this->makeDiamond([
            'paid_amount' => 120,
        ]));

        $this->assertSame(120, $dto->getPaidAmount());
        // 無償・合計の項目は DTO から無くなった
        $this->assertArrayNotHasKey('free_amount', $dto->toArray());
        $this->assertArrayNotHasKey('total_amount', $dto->toArray());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = DiamondBalanceAdapter::toDtoArray([
            $this->makeDiamond(['platform' => 'Apple']),
            $this->makeDiamond(['platform' => 'Google']),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame(['Apple', 'Google'], array_map(fn ($dto) => $dto->getPlatform(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], DiamondBalanceAdapter::toDtoArray([]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDiamond(array $overrides = []): TrxDiamond
    {
        $model = new TrxDiamond;
        $model->forceFill(array_merge([
            'sys_player_id' => 501,
            'platform' => 'Apple',
            'paid_amount' => 10,
        ], $overrides));

        return $model;
    }
}
