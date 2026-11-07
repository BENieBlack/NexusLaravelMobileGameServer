<?php

namespace Nexus\Core\Tests\Unit\ResourceDelivery\Handlers;

use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\Contracts\StaminaGranterInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\Handlers\StaminaDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * StaminaDeliveryHandler のユニットテスト
 *
 * 付与はStaminaGranterInterfaceの向こう側なので、
 * 呼び出し内容を記録するだけの実装を差し込んで検証する。
 */
class StaminaDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function スタミナだけをサポートする(): void
    {
        $handler = new StaminaDeliveryHandler(new RecordingStaminaGranter);

        $this->assertTrue($handler->supports(ResourceType::STAMINA));
        $this->assertTrue($handler->supports(ResourceType::STAMINA->value));

        // Walletで扱う資源やポイントは別Handlerが担当する
        $this->assertFalse($handler->supports(ResourceType::FOOD));
        $this->assertFalse($handler->supports(ResourceType::EXPERIENCE));
        $this->assertFalse($handler->supports('unknown_type'));
    }

    #[Test]
    public function 数量をそのまま付与する(): void
    {
        $granter = new RecordingStaminaGranter;
        $handler = new StaminaDeliveryHandler($granter);

        $handler->handle(777, $this->makeContent(30));

        $this->assertSame([[777, 30, null]], $granter->calls);
    }

    #[Test]
    public function metadataでスタミナ種別を指定できる(): void
    {
        $granter = new RecordingStaminaGranter;
        $handler = new StaminaDeliveryHandler($granter);

        $handler->handle(777, $this->makeContent(30, ['stamina_type' => 'event']));

        $this->assertSame([[777, 30, 'event']], $granter->calls);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function makeContent(int $amount, ?array $metadata = null): ResourceDeliveryContent
    {
        return new ResourceDeliveryContent(
            new Resource(ResourceType::STAMINA, 'stamina', $amount, null, $metadata)
        );
    }
}

/**
 * 付与内容を記録するだけのStaminaGranterInterface実装
 */
class RecordingStaminaGranter implements StaminaGranterInterface
{
    /** @var list<array{0: int, 1: int, 2: string|null}> */
    public array $calls = [];

    public function grantStamina(int $sysPlayerId, int $amount, ?string $staminaType = null): void
    {
        $this->calls[] = [$sysPlayerId, $amount, $staminaType];
    }
}
