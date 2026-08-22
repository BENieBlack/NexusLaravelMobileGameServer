<?php

namespace NexusResourceDelivery\Tests\Unit\Handlers;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Contracts\EquipmentRepositoryInterface;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\EquipmentDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EquipmentDeliveryHandler のユニットテスト
 *
 * 永続化はEquipmentRepositoryInterfaceの向こう側なので、
 * 呼び出し内容を記録するだけの実装を差し込んで検証する。
 */
class EquipmentDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function 装備系のタイプをすべてサポートする(): void
    {
        $handler = new EquipmentDeliveryHandler($this->makeRepository());

        foreach ([ResourceType::EQUIPMENT, ResourceType::WEAPON, ResourceType::ARMOR, ResourceType::ACCESSORY] as $type) {
            $this->assertTrue($handler->supports($type), "{$type->value} をサポートしていない");
            $this->assertTrue($handler->supports($type->value), "{$type->value} をサポートしていない");
        }
    }

    #[Test]
    public function 装備以外はサポートしない(): void
    {
        $handler = new EquipmentDeliveryHandler($this->makeRepository());

        $this->assertFalse($handler->supports(ResourceType::ITEM));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 付与先のプレイヤーidを引数のまま渡す(): void
    {
        $repository = $this->makeRepository();
        $handler = new EquipmentDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('equipment_sword_001'));

        $this->assertSame([[777, 'equipment_sword_001', null, null]], $repository->calls);
    }

    #[Test]
    public function 数量分だけ付与する(): void
    {
        $repository = $this->makeRepository();
        $handler = new EquipmentDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('equipment_sword_001', amount: 3));

        $this->assertCount(3, $repository->calls);
    }

    #[Test]
    public function 数量が0なら1件も付与しない(): void
    {
        $repository = $this->makeRepository();
        $handler = new EquipmentDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('equipment_sword_001', amount: 0));

        $this->assertSame([], $repository->calls);
    }

    #[Test]
    public function metadataのlevelとgradeを渡す(): void
    {
        $repository = $this->makeRepository();
        $handler = new EquipmentDeliveryHandler($repository);

        // 装備は insertEquipment(level, grade) の順で、ユニットとは並びが逆。
        // 取り違えても型エラーにならないため、違う値で確認する
        $handler->handle(777, $this->makeContent(
            'equipment_sword_001',
            metadata: ['level' => 20, 'grade' => 3]
        ));

        $this->assertSame([[777, 'equipment_sword_001', 20, 3]], $repository->calls);
    }

    #[Test]
    public function metadataに指定がなければnullを渡す(): void
    {
        $repository = $this->makeRepository();
        $handler = new EquipmentDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('equipment_sword_001', metadata: ['level' => 10]));

        $this->assertSame([[777, 'equipment_sword_001', 10, null]], $repository->calls);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function makeContent(string $mstEquipmentId, int $amount = 1, ?array $metadata = null): ResourceDeliveryContent
    {
        return new ResourceDeliveryContent(
            new Resource(ResourceType::EQUIPMENT, $mstEquipmentId, $amount, null, $metadata)
        );
    }

    private function makeRepository(): FakeEquipmentRepository
    {
        return new FakeEquipmentRepository;
    }
}

/**
 * 付与内容を記録するだけのEquipmentRepositoryInterface実装
 */
class FakeEquipmentRepository implements EquipmentRepositoryInterface
{
    /** @var list<array{0: int, 1: string, 2: int|null, 3: int|null}> */
    public array $calls = [];

    public function insertEquipment(int $sysPlayerId, string $mstEquipmentId, ?int $level = null, ?int $grade = null): void
    {
        $this->calls[] = [$sysPlayerId, $mstEquipmentId, $level, $grade];
    }
}
