<?php

namespace NexusResourceDelivery\Tests\Unit\Services;

use Nexus\Core\Support\CustomCollection;
use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryPolicy;
use NexusResourceDelivery\Enums\ResourceDeliveryResultReason;
use NexusResourceDelivery\Enums\ResourceDeliveryStatus;
use NexusResourceDelivery\Handlers\ResourceDeliveryHandlerInterface;
use NexusResourceDelivery\Managers\ResourceDeliveryManager;
use NexusResourceDelivery\Managers\ResourceDeliveryManagerInterface;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ResourceDeliveryService のテスト
 *
 * ガチャ・メール・課金の付与はすべてここを通る。
 * Handlerへの振り分けと、失敗したときに何が残って何が進むかが要点。
 *
 * Managerは依存の無い素のクラスなので、本物を使って
 * 配送前リストと送信完了リストの遷移まで通しで見る。
 * Logファサードを使うためLaravelのTestCaseを継承する。
 */
class ResourceDeliveryServiceTest extends TestCase
{
    private const PLAYER_ID = 1;

    private ResourceDeliveryManager $manager;

    private ResourceDeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new ResourceDeliveryManager;
        $this->service = new ResourceDeliveryService($this->manager);
    }

    #[Test]
    public function 登録したリソースをまとめて配送する(): void
    {
        $handler = $this->registerHandler([ResourceType::ITEM->value]);

        $this->service->addResources([
            Resource::item('item_potion', 3),
            Resource::item('item_elixir', 1),
        ]);

        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(2, $summary->getTotalCount());
        $this->assertSame(['item_potion', 'item_elixir'], $handler->handledIds());
        $this->assertSame([self::PLAYER_ID, self::PLAYER_ID], $handler->handledPlayerIds);
        $this->assertFalse($this->manager->hasPendingContents(), '配送済みは配送前リストから消える');
    }

    #[Test]
    public function 単一のリソースも配送できる(): void
    {
        $handler = $this->registerHandler([ResourceType::DIAMOND->value]);

        $this->service->addResource(Resource::diamond(100));
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(1, $summary->getTotalCount());
        $this->assertSame(100, $handler->handled[0]->getAmount());
    }

    #[Test]
    public function コレクションで渡しても配送できる(): void
    {
        $handler = $this->registerHandler([ResourceType::ITEM->value]);

        $this->service->addResources(new CustomCollection([
            Resource::item('item_potion', 1),
        ]));
        $this->service->deliver(self::PLAYER_ID);

        $this->assertCount(1, $handler->handled);
    }

    #[Test]
    public function 配送コンテンツを直接追加できる(): void
    {
        $handler = $this->registerHandler([ResourceType::ITEM->value]);

        $this->service->addContent(ResourceDeliveryContent::fromResource(Resource::item('item_potion', 1)));
        $this->service->addContents([
            ResourceDeliveryContent::fromResource(Resource::item('item_elixir', 1)),
        ]);
        $this->service->addContents(new CustomCollection([
            ResourceDeliveryContent::fromResource(Resource::item('item_ether', 1)),
        ]));

        $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(['item_potion', 'item_elixir', 'item_ether'], $handler->handledIds());
    }

    #[Test]
    public function タイプごとに対応するhandlerへ振り分ける(): void
    {
        $itemHandler = $this->registerHandler([ResourceType::ITEM->value]);
        $diamondHandler = $this->registerHandler([ResourceType::DIAMOND->value]);

        $this->service->addResources([
            Resource::item('item_potion', 1),
            Resource::diamond(100),
            Resource::item('item_elixir', 1),
        ]);
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(['item_potion', 'item_elixir'], $itemHandler->handledIds());
        $this->assertSame(['diamond'], $diamondHandler->handledIds());
        $this->assertSame(3, $summary->getTotalCount());
    }

    #[Test]
    public function 先に登録したhandlerが優先される(): void
    {
        // findHandlerは最初に見つかった1件を返す。
        // 後から同じタイプを拾うHandlerを足しても割り込めない
        $first = $this->registerHandler([ResourceType::ITEM->value]);
        $second = $this->registerHandler([ResourceType::ITEM->value]);

        $this->service->addResource(Resource::item('item_potion', 1));
        $this->service->deliver(self::PLAYER_ID);

        $this->assertCount(1, $first->handled);
        $this->assertCount(0, $second->handled);
    }

    #[Test]
    public function handlerが無いタイプは配送されず残る(): void
    {
        $this->registerHandler([ResourceType::ITEM->value]);

        $this->service->addResources([
            Resource::item('item_potion', 1),
            Resource::gold(500),
        ]);
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(1, $summary->getTotalCount(), 'Handlerが無いものはサマリーに入らない');
        $this->assertTrue($this->manager->hasPendingContents());
        $this->assertSame(
            ['gold'],
            $this->manager->getPendingContents()->map(fn ($content) => $content->getId())->values()->all(),
            '配送できなかったgoldだけが残る'
        );
    }

    #[Test]
    public function handlerが失敗しても他のコンテンツの配送は続く(): void
    {
        $handler = $this->registerHandler([ResourceType::ITEM->value]);
        $handler->throwFor = 'item_broken';

        $this->service->addResources([
            Resource::item('item_potion', 1),
            Resource::item('item_broken', 1),
            Resource::item('item_elixir', 1),
        ]);
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(['item_potion', 'item_broken', 'item_elixir'], $handler->handledIds());

        // 失敗したものも送信完了として扱い、配送前リストには戻さない
        $this->assertSame(3, $summary->getTotalCount());
        $this->assertFalse($this->manager->hasPendingContents());
    }

    #[Test]
    public function 配送に成功したコンテンツは受取済みになる(): void
    {
        $this->registerHandler([ResourceType::ITEM->value]);

        $this->service->addResource(Resource::item('item_potion', 1));
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(ResourceDeliveryStatus::RECEIVED, $summary->getContents()->first()->getStatus());
    }

    #[Test]
    public function メールボックスへ回したコンテンツは配送済みどまりになる(): void
    {
        // Handlerが失敗理由を立てると、即時受取ではなくDELIVEREDになる
        $handler = $this->registerHandler([ResourceType::DIAMOND->value]);
        $handler->failureReason = ResourceDeliveryResultReason::SEND_TO_MAILBOX;

        $this->service->addResource(Resource::diamond(100));
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(ResourceDeliveryStatus::DELIVERED, $summary->getContents()->first()->getStatus());
    }

    #[Test]
    public function 配送中に追加されたリソースも同じ呼び出しで配送される(): void
    {
        // 重複ユニットを欠片に変える等、配送の途中で別のリソースが増える
        $itemHandler = $this->registerHandler([ResourceType::ITEM->value]);
        $unitHandler = $this->registerHandler([ResourceType::UNIT->value]);
        $unitHandler->onHandle = function () {
            $this->service->addResource(Resource::item('item_shard', 10));
        };

        $this->service->addResource(Resource::unit('unit_001', 1));
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(['item_shard'], $itemHandler->handledIds());
        $this->assertSame(2, $summary->getTotalCount(), '元のユニットと追加された欠片の両方が入る');
    }

    #[Test]
    public function 連鎖は2周までで打ち切る(): void
    {
        // 無限に増え続けるHandlerが居ても、ループは2周で止める
        $handler = $this->registerHandler([ResourceType::ITEM->value]);
        $handler->onHandle = function () {
            $this->service->addResource(Resource::item('item_shard', 1));
        };

        $this->service->addResource(Resource::item('item_potion', 1));
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(2, $summary->getTotalCount());
        $this->assertTrue($this->manager->hasPendingContents(), '3周目の分は配送されずに残る');
    }

    #[Test]
    public function 配送するものが無ければ空のサマリーが返る(): void
    {
        $this->registerHandler([ResourceType::ITEM->value]);

        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(0, $summary->getTotalCount());
    }

    #[Test]
    public function 数量が0のリソースは登録されない(): void
    {
        $handler = $this->registerHandler([ResourceType::ITEM->value]);

        $this->service->addResource(Resource::item('item_potion', 0));
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertCount(0, $handler->handled);
        $this->assertSame(0, $summary->getTotalCount());
    }

    #[Test]
    public function 上限超過で例外を投げるポリシーなら例外になる(): void
    {
        $handler = $this->registerHandler([ResourceType::DIAMOND->value]);
        $handler->failureReason = ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED;

        $this->service->addResource(Resource::diamond(100));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ダイヤの所持上限です');

        $this->service->deliver(
            self::PLAYER_ID,
            ResourceDeliveryPolicy::createThrowErrorWhenResourceLimitReachedPolicy(
                new \RuntimeException('ダイヤの所持上限です')
            )
        );
    }

    #[Test]
    public function 既定のポリシーは上限超過でも例外にしない(): void
    {
        // 既定はメールボックス送信なので、配送は成功として返す
        $handler = $this->registerHandler([ResourceType::DIAMOND->value]);
        $handler->failureReason = ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED;

        $this->service->addResource(Resource::diamond(100));
        $summary = $this->service->deliver(self::PLAYER_ID);

        $this->assertSame(1, $summary->getTotalCount());
    }

    #[Test]
    public function ポリシーの対象外タイプは上限超過でも例外にならない(): void
    {
        // ポリシーはdiamondだけを対象にしているのでitemは素通りする
        $handler = $this->registerHandler([ResourceType::ITEM->value]);
        $handler->failureReason = ResourceDeliveryResultReason::INVENTORY_FULL;

        $this->service->addResource(Resource::item('item_potion', 1));

        $policy = new ResourceDeliveryPolicy;
        $summary = $this->service->deliver(self::PLAYER_ID, $policy);

        $this->assertSame(1, $summary->getTotalCount());
    }

    #[Test]
    public function 配送処理そのものが落ちたら例外はそのまま出る(): void
    {
        $manager = $this->createMock(ResourceDeliveryManagerInterface::class);
        $manager->method('hasPendingContents')->willReturn(true);
        $manager->method('getPendingContents')->willThrowException(new \RuntimeException('DBが落ちた'));

        $service = new ResourceDeliveryService($manager);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DBが落ちた');

        $service->deliver(self::PLAYER_ID);
    }

    #[Test]
    public function サポートするタイプは全handlerの和集合になる(): void
    {
        $this->registerHandler([ResourceType::ITEM->value, ResourceType::CONSUMABLE->value]);
        $this->registerHandler([ResourceType::ITEM->value, ResourceType::DIAMOND->value]);

        $types = $this->service->supportedTypes();

        $this->assertEqualsCanonicalizing(
            [ResourceType::ITEM->value, ResourceType::CONSUMABLE->value, ResourceType::DIAMOND->value],
            array_values($types)
        );
    }

    #[Test]
    public function handlerが無ければサポートするタイプも空(): void
    {
        $this->assertSame([], $this->service->supportedTypes());
    }

    /**
     * 指定したタイプを受け持つHandlerを登録して返す
     *
     * @param  list<string>  $supportedTypes
     */
    private function registerHandler(array $supportedTypes): SpyDeliveryHandler
    {
        $handler = new SpyDeliveryHandler($supportedTypes);
        $this->service->registerHandler($handler);

        return $handler;
    }
}

/**
 * 何を渡されたかを記録するだけのHandler
 */
class SpyDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    /** @var list<ResourceDeliveryContent> 渡されたコンテンツ */
    public array $handled = [];

    /** @var list<int> 渡されたプレイヤーID */
    public array $handledPlayerIds = [];

    /** このマスターIDのときだけ例外を投げる */
    public ?string $throwFor = null;

    /** 配送のたびに立てる失敗理由 */
    public ?ResourceDeliveryResultReason $failureReason = null;

    /** 配送のたびに実行する追加処理 */
    public ?\Closure $onHandle = null;

    /**
     * @param  list<string>  $supportedTypes
     */
    public function __construct(private array $supportedTypes) {}

    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        $this->handled[] = $resourceDeliveryContent;
        $this->handledPlayerIds[] = $sysPlayerId;

        if ($this->throwFor !== null && $resourceDeliveryContent->getId() === $this->throwFor) {
            throw new \RuntimeException('配送に失敗した');
        }

        if ($this->failureReason !== null) {
            $resourceDeliveryContent->setFailureReason($this->failureReason);
        }

        if ($this->onHandle !== null) {
            ($this->onHandle)();
        }
    }

    public function supports(ResourceType|string $type): bool
    {
        $typeValue = $type instanceof ResourceType ? $type->value : $type;

        return in_array($typeValue, $this->supportedTypes, true);
    }

    /**
     * 渡されたコンテンツのマスターIDを並び順のまま返す
     *
     * @return list<string>
     */
    public function handledIds(): array
    {
        return array_map(fn (ResourceDeliveryContent $content) => $content->getId(), $this->handled);
    }
}
