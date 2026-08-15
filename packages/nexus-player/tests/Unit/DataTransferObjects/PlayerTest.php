<?php

namespace NexusPlayer\Tests\Unit\DataTransferObjects;

use NexusPlayer\DataTransferObjects\Player;
use PHPUnit\Framework\TestCase;

class PlayerDtoTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $dto = new Player(
            id: 1,
            uuid: 'uuid-123',
            myId: 'player001',
            name: 'Test Player',
            level: 10,
            levelExp: 500,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 12:00:00'
        );

        $this->assertSame(1, $dto->getId());
        $this->assertSame('uuid-123', $dto->getUuid());
        $this->assertSame('player001', $dto->getMyId());
        $this->assertSame('Test Player', $dto->getName());
        $this->assertSame(10, $dto->getLevel());
        $this->assertSame(500, $dto->getLevelExp());
        $this->assertSame('2026-01-01 00:00:00', $dto->getCreatedAt());
        $this->assertSame('2026-01-01 12:00:00', $dto->getUpdatedAt());
    }

    public function test_set_name_updates_value(): void
    {
        $dto = new Player(
            id: 1,
            uuid: 'uuid-123',
            myId: 'player001',
            name: 'Old Name',
            level: 1,
            levelExp: 0,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00'
        );

        $dto->setName('New Name');

        $this->assertSame('New Name', $dto->getName());
    }

    public function test_set_level_updates_value(): void
    {
        $dto = new Player(
            id: 1,
            uuid: 'uuid-123',
            myId: 'player001',
            name: 'Player',
            level: 1,
            levelExp: 0,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00'
        );

        $dto->setLevel(50);

        $this->assertSame(50, $dto->getLevel());
    }

    public function test_set_level_exp_updates_value(): void
    {
        $dto = new Player(
            id: 1,
            uuid: 'uuid-123',
            myId: 'player001',
            name: 'Player',
            level: 1,
            levelExp: 0,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00'
        );

        $dto->setLevelExp(1000);

        $this->assertSame(1000, $dto->getLevelExp());
    }
}
