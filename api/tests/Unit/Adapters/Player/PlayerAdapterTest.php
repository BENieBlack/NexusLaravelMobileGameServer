<?php

namespace Tests\Unit\Adapters\Player;

use App\Adapters\Player\PlayerAdapter;
use App\Models\Sys\SysPlayer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PlayerAdapter の Model→DTO 変換テスト
 *
 * created_at は Model 側で Y-m-d H:i:s に整形され、
 * updated_at は生の属性をキャストしているという非対称があるため、
 * どちらも文字列として揃うことを検証する。
 */
class PlayerAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $dto = PlayerAdapter::toDto($this->makePlayer());

        $this->assertSame(501, $dto->getId());
        $this->assertSame('11111111-2222-3333-4444-555555555555', $dto->getUuid());
        $this->assertSame('MYID0001', $dto->getMyId());
        $this->assertSame('テストプレイヤー', $dto->getName());
        $this->assertSame(12, $dto->getLevel());
        $this->assertSame(3400, $dto->getLevelExp());
    }

    #[Test]
    public function test_timestamps_are_returned_as_string(): void
    {
        $dto = PlayerAdapter::toDto($this->makePlayer());

        $this->assertSame('2026-01-01 00:00:00', $dto->getCreatedAt());
        $this->assertSame('2026-01-02 09:30:00', $dto->getUpdatedAt());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = PlayerAdapter::toDtoArray([
            $this->makePlayer(['id' => 1]),
            $this->makePlayer(['id' => 2]),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame([1, 2], array_map(fn ($dto) => $dto->getId(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], PlayerAdapter::toDtoArray([]));
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
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-02 09:30:00',
        ], $overrides));

        return $model;
    }
}
