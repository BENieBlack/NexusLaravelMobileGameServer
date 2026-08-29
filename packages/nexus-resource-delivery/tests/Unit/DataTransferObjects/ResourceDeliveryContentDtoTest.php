<?php

namespace NexusResourceDelivery\Tests\Unit\DataTransferObjects;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Enums\ResourceDeliveryResultReason;
use NexusResourceDelivery\Enums\ResourceDeliveryStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ResourceDeliveryContent のユニットテスト
 *
 * Resourceに配送の状態を足したもの。
 * 配送ステータスの遷移と、変換したときに元のリソースが残ることが要点。
 */
class ResourceDeliveryContentDtoTest extends TestCase
{
    #[Test]
    public function リソースから生成すると未送信で始まる(): void
    {
        $content = ResourceDeliveryContent::fromResource(Resource::item('item_potion', 3));

        $this->assertSame(ResourceDeliveryStatus::PENDING, $content->getStatus());
        $this->assertFalse($content->isSendComplete());
        $this->assertFalse($content->hasFailed());
        $this->assertFalse($content->isConverted());
        $this->assertSame(ResourceDeliveryResultReason::NONE, $content->getFailureReason());
        $this->assertSame(ResourceDeliveryResultReason::NONE, $content->getConversionReason());
        $this->assertNull($content->getOriginalResource());
    }

    #[Test]
    public function 一意なidが振られる(): void
    {
        $resource = Resource::item('item_potion', 1);

        // 同じリソースから作ってもDeliveryManagerが別物として扱えること
        $this->assertNotSame(
            ResourceDeliveryContent::fromResource($resource)->getUniqueId(),
            ResourceDeliveryContent::fromResource($resource)->getUniqueId()
        );
    }

    #[Test]
    public function リソースの内容はそのまま読み出せる(): void
    {
        $resource = Resource::gold(500, '2026-12-31 23:59:59');
        $content = ResourceDeliveryContent::fromResource($resource);

        $this->assertSame($resource, $content->getResource());
        $this->assertSame(ResourceType::GOLD, $content->getType());
        $this->assertSame('gold', $content->getTypeValue());
        $this->assertSame('gold', $content->getId());
        $this->assertSame(500, $content->getAmount());
        $this->assertSame('2026-12-31 23:59:59', $content->getExpireAt());
        $this->assertNull($content->getMetadata());
        $this->assertTrue($content->isValid());
    }

    #[Test]
    public function メタデータも読み出せる(): void
    {
        $content = ResourceDeliveryContent::fromResource(Resource::unit('unit_001', 1, grade: 3, level: 10));

        $this->assertSame(['grade' => 3, 'level' => 10], $content->getMetadata());
    }

    #[Test]
    public function 数量が0以下なら無効(): void
    {
        $this->assertFalse(ResourceDeliveryContent::fromResource(Resource::item('item_potion', 0))->isValid());
    }

    #[Test]
    public function 配列からも生成できる(): void
    {
        $content = ResourceDeliveryContent::fromArray([
            'type' => 'item',
            'id' => 'item_potion',
            'amount' => 3,
        ]);

        $this->assertSame('item_potion', $content->getId());
        $this->assertSame(3, $content->getAmount());
        $this->assertSame(ResourceDeliveryStatus::PENDING, $content->getStatus());
    }

    #[Test]
    public function 配送に成功したら受取済みになる(): void
    {
        $content = ResourceDeliveryContent::fromResource(Resource::item('item_potion', 1));

        $content->markAsSendComplete();

        $this->assertSame(ResourceDeliveryStatus::RECEIVED, $content->getStatus());
        $this->assertTrue($content->isSendComplete());
    }

    #[Test]
    public function 失敗理由が立っていれば配送済みどまりになる(): void
    {
        // 即時付与できずメールボックスへ回した場合。
        // プレイヤーが受け取るまでRECEIVEDにはしない
        $content = ResourceDeliveryContent::fromResource(Resource::diamond(100));
        $content->setFailureReason(ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED);

        $content->markAsSendComplete();

        $this->assertSame(ResourceDeliveryStatus::DELIVERED, $content->getStatus());
        $this->assertTrue($content->hasFailed());
        $this->assertTrue($content->isSendComplete(), '配送済みも送信完了として扱う');
    }

    #[Test]
    public function ステータスは直接指定もできる(): void
    {
        $content = ResourceDeliveryContent::fromResource(Resource::item('item_potion', 1));

        $content->markAsDelivered();
        $this->assertSame(ResourceDeliveryStatus::DELIVERED, $content->getStatus());

        $content->markAsReceived();
        $this->assertSame(ResourceDeliveryStatus::RECEIVED, $content->getStatus());
    }

    #[Test]
    public function 変換すると元のリソースが残る(): void
    {
        $original = Resource::unit('unit_001', 1);
        $content = ResourceDeliveryContent::fromResource($original);

        $content->convertTo(Resource::item('item_shard', 10), ResourceDeliveryResultReason::DUPLICATED_UNIT);

        $this->assertSame('item_shard', $content->getId());
        $this->assertSame(10, $content->getAmount());
        $this->assertSame($original, $content->getOriginalResource());
        $this->assertTrue($content->isConverted());
        $this->assertSame(ResourceDeliveryResultReason::DUPLICATED_UNIT, $content->getConversionReason());
    }

    #[Test]
    public function 二重に変換しても一番最初のリソースを保持する(): void
    {
        // ログには「プレイヤーが何を得るはずだったか」を残したいので、
        // 中間の状態で上書きしない
        $original = Resource::unit('unit_001', 1);
        $content = ResourceDeliveryContent::fromResource($original);

        $content->convertTo(Resource::item('item_shard', 10), ResourceDeliveryResultReason::DUPLICATED_UNIT);
        $content->convertTo(Resource::gold(100), ResourceDeliveryResultReason::INVENTORY_FULL);

        $this->assertSame($original, $content->getOriginalResource());
        $this->assertSame(ResourceDeliveryResultReason::INVENTORY_FULL, $content->getConversionReason());
    }

    #[Test]
    public function ログ用の増減量を持てる(): void
    {
        $content = ResourceDeliveryContent::fromResource(Resource::gold(500));

        $this->assertSame(0, $content->getBeforeAmount());
        $this->assertSame(0, $content->getAfterAmount());

        $content->setBeforeAmount(1000);
        $content->setAfterAmount(1500);

        $this->assertSame(1000, $content->getBeforeAmount());
        $this->assertSame(1500, $content->getAfterAmount());
    }

    #[Test]
    public function 配列に変換できる(): void
    {
        $content = ResourceDeliveryContent::fromResource(Resource::item('item_potion', 3));
        $content->setBeforeAmount(1);
        $content->setAfterAmount(4);

        $array = $content->toArray();

        $this->assertSame($content->getUniqueId(), $array['unique_id']);
        $this->assertSame('item_potion', $array['resource']['id']);
        $this->assertSame('pending', $array['status']);
        $this->assertSame('none', $array['conversion_reason']);
        $this->assertSame('none', $array['failure_reason']);
        $this->assertNull($array['original_resource']);
        $this->assertSame(1, $array['before_amount']);
        $this->assertSame(4, $array['after_amount']);
    }

    #[Test]
    public function 変換したあとの配列には変換前のリソースも入る(): void
    {
        $content = ResourceDeliveryContent::fromResource(Resource::unit('unit_001', 1));
        $content->convertTo(Resource::item('item_shard', 10), ResourceDeliveryResultReason::DUPLICATED_UNIT);
        $content->setFailureReason(ResourceDeliveryResultReason::SEND_TO_MAILBOX);
        $content->markAsSendComplete();

        $array = $content->toArray();

        $this->assertSame('item_shard', $array['resource']['id']);
        $this->assertSame('unit_001', $array['original_resource']['id']);
        $this->assertSame('duplicated_unit', $array['conversion_reason']);
        $this->assertSame('send_to_mailbox', $array['failure_reason']);
        $this->assertSame('delivered', $array['status']);
    }
}
