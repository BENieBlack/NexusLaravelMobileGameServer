<?php

namespace NexusResourceDelivery\Tests\Unit\Handlers;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Contracts\ExperienceGranterInterface;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\ExperienceDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ExperienceDeliveryHandler のユニットテスト
 *
 * 付与はExperienceGranterInterfaceの向こう側なので、
 * 呼び出し内容を記録するだけの実装を差し込んで検証する。
 */
class ExperienceDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function 経験値だけをサポートする(): void
    {
        $handler = new ExperienceDeliveryHandler(new RecordingExperienceGranter);

        $this->assertTrue($handler->supports(ResourceType::EXPERIENCE));
        $this->assertTrue($handler->supports(ResourceType::EXPERIENCE->value));

        $this->assertFalse($handler->supports(ResourceType::FOOD));
        $this->assertFalse($handler->supports(ResourceType::STAMINA));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 指定がなければプレイヤー経験値として付与する(): void
    {
        $granter = new RecordingExperienceGranter;
        $handler = new ExperienceDeliveryHandler($granter);

        $handler->handle(777, $this->makeContent(500));

        $this->assertSame([[777, 500, 'player', null]], $granter->calls);
    }

    #[Test]
    public function metadataで付与先を指定できる(): void
    {
        $granter = new RecordingExperienceGranter;
        $handler = new ExperienceDeliveryHandler($granter);

        $handler->handle(777, $this->makeContent(500, [
            'target_type' => 'unit',
            'target_id' => 'unit_001',
        ]));

        $this->assertSame([[777, 500, 'unit', 'unit_001']], $granter->calls);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function makeContent(int $amount, ?array $metadata = null): ResourceDeliveryContent
    {
        return new ResourceDeliveryContent(
            new Resource(ResourceType::EXPERIENCE, 'experience', $amount, null, $metadata)
        );
    }
}

/**
 * 付与内容を記録するだけのExperienceGranterInterface実装
 */
class RecordingExperienceGranter implements ExperienceGranterInterface
{
    /** @var list<array{0: int, 1: int, 2: string, 3: string|null}> */
    public array $calls = [];

    public function grantExperience(
        int $sysPlayerId,
        int $amount,
        string $targetType = self::TARGET_PLAYER,
        ?string $targetId = null
    ): void {
        $this->calls[] = [$sysPlayerId, $amount, $targetType, $targetId];
    }
}
