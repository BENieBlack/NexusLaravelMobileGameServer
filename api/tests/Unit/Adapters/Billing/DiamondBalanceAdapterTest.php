<?php

namespace Tests\Unit\Adapters\Billing;

use App\Adapters\Billing\DiamondBalanceAdapter;
use App\Models\Trx\TrxDiamond;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DiamondBalanceAdapter の Model→DTO 変換テスト
 *
 * 有償/無償の残高はプラットフォームごとに分かれて持つため、
 * 取り違えとプラットフォームの取りこぼしがないことを検証する。
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
    public function test_paid_and_free_amounts_are_not_swapped(): void
    {
        $dto = DiamondBalanceAdapter::toDto($this->makeDiamond([
            'paid_amount' => 120,
            'free_amount' => 30,
        ]));

        $this->assertSame(120, $dto->getPaidAmount());
        $this->assertSame(30, $dto->getFreeAmount());
        $this->assertSame(150, $dto->getTotalAmount());
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
            'free_amount' => 20,
        ], $overrides));

        return $model;
    }
}
