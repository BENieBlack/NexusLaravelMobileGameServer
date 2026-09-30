<?php

namespace Tests\Unit\Adapters\Stamina;

use App\Adapters\Stamina\StaminaAdapter;
use App\Models\Trx\TrxStamina;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StaminaAdapter の Model→DTO 変換テスト
 *
 * recovery_rate_multiplier は decimal:2 キャストで文字列として返るため、
 * float を要求するDTOへ渡したときに壊れないことを検証する。
 */
class StaminaAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $dto = StaminaAdapter::toDto($this->makeStamina());

        $this->assertSame(501, $dto->getSysPlayerId());
        $this->assertSame('normal', $dto->getType());
        $this->assertSame(42, $dto->getCurrentStamina());
        $this->assertSame('2026-01-01 00:00:00', $dto->getLastRecoveryAt());
    }

    #[Test]
    public function test_recovery_rate_multiplier_is_converted_to_float(): void
    {
        $dto = StaminaAdapter::toDto($this->makeStamina(['recovery_rate_multiplier' => 1.5]));

        $this->assertIsFloat($dto->getRecoveryRateMultiplier());
        $this->assertSame(1.5, $dto->getRecoveryRateMultiplier());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = StaminaAdapter::toDtoArray([
            $this->makeStamina(['type' => 'normal']),
            $this->makeStamina(['type' => 'raid']),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame(['normal', 'raid'], array_map(fn ($dto) => $dto->getType(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], StaminaAdapter::toDtoArray([]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeStamina(array $overrides = []): TrxStamina
    {
        $model = new TrxStamina;
        $model->forceFill(array_merge([
            'sys_player_id' => 501,
            'type' => 'normal',
            'current_stamina' => 42,
            'recovery_rate_multiplier' => 1.0,
            'last_recovery_at' => '2026-01-01 00:00:00',
        ], $overrides));

        return $model;
    }
}
