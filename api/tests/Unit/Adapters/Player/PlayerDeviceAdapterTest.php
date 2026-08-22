<?php

namespace Tests\Unit\Adapters\Player;

use App\Adapters\Player\PlayerDeviceAdapter;
use App\Models\Sys\SysPlayerDevice;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PlayerDeviceAdapter の Model→DTO 変換テスト
 *
 * device_info は array キャストのJSONカラムなので、
 * 配列のまま DTO に渡ることを検証する。
 */
class PlayerDeviceAdapterTest extends TestCase
{
    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $dto = PlayerDeviceAdapter::toDto($this->makeDevice());

        $this->assertSame(77, $dto->getId());
        $this->assertSame(501, $dto->getSysPlayerId());
        $this->assertSame('device-uuid-0001', $dto->getUuid());
        $this->assertSame('2026-01-02 09:30:00', $dto->getLastLoginAt());
        $this->assertSame('2026-01-01 00:00:00', $dto->getCreatedAt());
        $this->assertSame('2026-01-02 09:30:00', $dto->getUpdatedAt());
    }

    #[Test]
    public function test_device_info_is_passed_through_as_array(): void
    {
        $dto = PlayerDeviceAdapter::toDto($this->makeDevice([
            'device_info' => ['os' => 'iOS', 'version' => '18.2'],
        ]));

        $this->assertSame(['os' => 'iOS', 'version' => '18.2'], $dto->getDeviceInfo());
    }

    #[Test]
    public function test_device_info_can_be_null(): void
    {
        $dto = PlayerDeviceAdapter::toDto($this->makeDevice(['device_info' => null]));

        $this->assertNull($dto->getDeviceInfo());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = PlayerDeviceAdapter::toDtoArray([
            $this->makeDevice(['id' => 1]),
            $this->makeDevice(['id' => 2]),
        ]);

        $this->assertCount(2, $dtos);
        $this->assertSame([1, 2], array_map(fn ($dto) => $dto->getId(), $dtos));
    }

    #[Test]
    public function test_converts_empty_iterable_to_empty_array(): void
    {
        $this->assertSame([], PlayerDeviceAdapter::toDtoArray([]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDevice(array $overrides = []): SysPlayerDevice
    {
        $model = new SysPlayerDevice;
        $model->forceFill(array_merge([
            'id' => 77,
            'sys_player_id' => 501,
            'uuid' => 'device-uuid-0001',
            'device_info' => ['os' => 'Android'],
            'last_login_at' => '2026-01-02 09:30:00',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-02 09:30:00',
        ], $overrides));

        return $model;
    }
}
