<?php

namespace NexusResourceDelivery\Tests\Unit\Handlers;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Contracts\UnitRepositoryInterface;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\UnitDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UnitDeliveryHandler のユニットテスト
 *
 * 永続化はUnitRepositoryInterfaceの向こう側なので、
 * 呼び出し内容を記録するだけの実装を差し込んで検証する。
 */
class UnitDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function unitタイプをサポートする(): void
    {
        $handler = new UnitDeliveryHandler($this->makeRepository());

        $this->assertTrue($handler->supports(ResourceType::UNIT));
        $this->assertTrue($handler->supports(ResourceType::UNIT->value));
    }

    #[Test]
    public function unit以外はサポートしない(): void
    {
        $handler = new UnitDeliveryHandler($this->makeRepository());

        $this->assertFalse($handler->supports(ResourceType::ITEM));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 付与先のプレイヤーidを引数のまま渡す(): void
    {
        $repository = $this->makeRepository();
        $handler = new UnitDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('unit_knight_001'));

        $this->assertSame([[777, 'unit_knight_001', null, null]], $repository->calls);
    }

    #[Test]
    public function 数量分だけ付与する(): void
    {
        $repository = $this->makeRepository();
        $handler = new UnitDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('unit_knight_001', amount: 3));

        $this->assertCount(3, $repository->calls);
    }

    #[Test]
    public function 数量が0なら1件も付与しない(): void
    {
        $repository = $this->makeRepository();
        $handler = new UnitDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('unit_knight_001', amount: 0));

        $this->assertSame([], $repository->calls);
    }

    #[Test]
    public function metadataのgradeとlevelを渡す(): void
    {
        $repository = $this->makeRepository();
        $handler = new UnitDeliveryHandler($repository);

        // gradeとlevelは並び順を取り違えても型エラーにならないため、違う値で確認する
        $handler->handle(777, $this->makeContent(
            'unit_knight_001',
            metadata: ['grade' => 3, 'level' => 20]
        ));

        $this->assertSame([[777, 'unit_knight_001', 3, 20]], $repository->calls);
    }

    #[Test]
    public function metadataに指定がなければnullを渡す(): void
    {
        $repository = $this->makeRepository();
        $handler = new UnitDeliveryHandler($repository);

        $handler->handle(777, $this->makeContent('unit_knight_001', metadata: ['grade' => 5]));

        $this->assertSame([[777, 'unit_knight_001', 5, null]], $repository->calls);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function makeContent(string $mstUnitId, int $amount = 1, ?array $metadata = null): ResourceDeliveryContent
    {
        return new ResourceDeliveryContent(
            new Resource(ResourceType::UNIT, $mstUnitId, $amount, null, $metadata)
        );
    }

    private function makeRepository(): FakeUnitRepository
    {
        return new FakeUnitRepository;
    }
}

/**
 * 付与内容を記録するだけのUnitRepositoryInterface実装
 */
class FakeUnitRepository implements UnitRepositoryInterface
{
    /** @var list<array{0: int, 1: string, 2: int|null, 3: int|null}> */
    public array $calls = [];

    public function insertUnit(int $sysPlayerId, string $mstUnitId, ?int $grade = null, ?int $level = null): void
    {
        $this->calls[] = [$sysPlayerId, $mstUnitId, $grade, $level];
    }
}
