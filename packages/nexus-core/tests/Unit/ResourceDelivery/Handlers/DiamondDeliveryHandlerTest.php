<?php

namespace Nexus\Core\Tests\Unit\ResourceDelivery\Handlers;

use Nexus\Core\Resource\DataTransferObjects\DiamondBalance;
use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\Resource\Services\DiamondService;
use Nexus\Core\ResourceDelivery\Contracts\DefaultPlatformProviderInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\Handlers\DiamondDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DiamondDeliveryHandler の platform の決め方のユニットテスト
 *
 * 優先順は metadata['platform'] → 現在の platform（Provider）→ Apple。
 * DiamondService は、引数の platform を記録するだけのフェイクを使う（API・DB・Laravel に依存しない）。
 */
class DiamondDeliveryHandlerTest extends TestCase
{
    #[Test]
    public function providerを渡さない1引数のコンストラクタで従来どおりappleに入る(): void
    {
        $service = new RecordingDiamondService;
        $handler = new DiamondDeliveryHandler($service);

        $handler->handle(1, $this->makeContent());

        $this->assertSame(['Apple'], $service->platforms);
    }

    #[Test]
    public function providerがnullを返すときはappleに入る(): void
    {
        $service = new RecordingDiamondService;
        $handler = new DiamondDeliveryHandler($service, new FakePlatformProvider(null));

        $handler->handle(1, $this->makeContent());

        $this->assertSame(['Apple'], $service->platforms);
    }

    #[Test]
    public function providerがplatformを返すときはmetadataが無ければその値に入る(): void
    {
        $service = new RecordingDiamondService;
        $handler = new DiamondDeliveryHandler($service, new FakePlatformProvider('Google'));

        $handler->handle(1, $this->makeContent());

        $this->assertSame(['Google'], $service->platforms);
    }

    #[Test]
    public function metadataのplatformはproviderより優先する(): void
    {
        $service = new RecordingDiamondService;
        $handler = new DiamondDeliveryHandler($service, new FakePlatformProvider('Google'));

        $handler->handle(1, $this->makeContent(['platform' => 'Apple']));

        $this->assertSame(['Apple'], $service->platforms);
    }

    #[Test]
    public function 有償ダイヤだけをサポートし無償ダイヤの種別は受けない(): void
    {
        // 無償ダイヤは item + free_diamond で ItemDeliveryHandler が受ける。
        // 廃止した diamond をこの Handler が受けて黙って付与することは無い
        $handler = new DiamondDeliveryHandler(new RecordingDiamondService);

        $this->assertTrue($handler->supports(ResourceType::PAID_DIAMOND));
        $this->assertTrue($handler->supports('paid_diamond'));
        $this->assertFalse($handler->supports('diamond'));
        $this->assertFalse($handler->supports(ResourceType::ITEM));
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function makeContent(?array $metadata = null): ResourceDeliveryContent
    {
        return new ResourceDeliveryContent(new Resource(ResourceType::PAID_DIAMOND, 'paid_diamond', 10, null, $metadata));
    }
}

class FakePlatformProvider implements DefaultPlatformProviderInterface
{
    public function __construct(private readonly ?string $platform) {}

    public function findCurrentPlatform(): ?string
    {
        return $this->platform;
    }
}

/**
 * addPaidDiamond に渡された platform を記録する DiamondService
 */
class RecordingDiamondService extends DiamondService
{
    /** @var list<string> */
    public array $platforms = [];

    public function __construct() {}

    public function addPaidDiamond(int $sysPlayerId, string $platform, int $amount): DiamondBalance
    {
        $this->platforms[] = $platform;

        return new DiamondBalance($sysPlayerId, $platform, 0);
    }
}
