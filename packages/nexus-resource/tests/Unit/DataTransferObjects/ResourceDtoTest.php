<?php

namespace NexusResource\Tests\Unit\DataTransferObjects;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Resource のユニットテスト
 *
 * ガチャ報酬・配送・アイテム付与が共通で通るDTO。
 * ファクトリが正しいタイプとマスターIDを組み立てること、
 * 配列との相互変換が壊れないことを確認する。
 */
class ResourceDtoTest extends TestCase
{
    #[Test]
    public function 生成時に一意なidが振られる(): void
    {
        $one = Resource::diamond(10);
        $other = Resource::diamond(10);

        $this->assertNotSame('', $one->getUniqueId());
        $this->assertNotSame($one->getUniqueId(), $other->getUniqueId(), '同じ内容でも別物として扱える');
    }

    #[Test]
    public function タイプは列挙型と文字列の両方で取れる(): void
    {
        $resource = Resource::item('item_potion', 3);

        $this->assertSame(ResourceType::ITEM, $resource->getType());
        $this->assertSame('item', $resource->getTypeValue());
        $this->assertSame('item_potion', $resource->getId());
        $this->assertSame(3, $resource->getAmount());
    }

    #[Test]
    public function 数量とメタデータは後から変更できる(): void
    {
        $resource = Resource::item('item_potion', 1);

        $resource->setAmount(5);
        $resource->setMetadata(['from' => 'gacha']);

        $this->assertSame(5, $resource->getAmount());
        $this->assertSame(['from' => 'gacha'], $resource->getMetadata());
    }

    #[Test]
    public function 数量が1以上なら有効(): void
    {
        $this->assertTrue(Resource::item('item_potion', 1)->isValid());
        $this->assertFalse(Resource::item('item_potion', 0)->isValid());
        $this->assertFalse(Resource::item('item_potion', -1)->isValid());
    }

    #[Test]
    public function 通貨系のファクトリ(): void
    {
        $this->assertResource(Resource::diamond(100), ResourceType::DIAMOND, 'diamond', 100);
        $this->assertResource(Resource::paidDiamond(50), ResourceType::PAID_DIAMOND, 'paid_diamond', 50);
        $this->assertResource(Resource::gold(200), ResourceType::GOLD, 'gold', 200);
        $this->assertResource(Resource::coin(300), ResourceType::COIN, 'coin', 300);
    }

    #[Test]
    public function 通貨は有効期限を持てる(): void
    {
        $gold = Resource::gold(100, '2026-12-31 23:59:59');

        $this->assertSame('2026-12-31 23:59:59', $gold->getExpireAt());
        $this->assertNull(Resource::gold(100)->getExpireAt());
    }

    #[Test]
    public function 自然資源とスタミナ経験値のファクトリ(): void
    {
        $this->assertResource(Resource::food(1), ResourceType::FOOD, 'food', 1);
        $this->assertResource(Resource::wood(2), ResourceType::WOOD, 'wood', 2);
        $this->assertResource(Resource::stone(3), ResourceType::STONE, 'stone', 3);
        $this->assertResource(Resource::iron(4), ResourceType::IRON, 'iron', 4);
        $this->assertResource(Resource::stamina(5), ResourceType::STAMINA, 'stamina', 5);
        $this->assertResource(Resource::experience(6), ResourceType::EXPERIENCE, 'experience', 6);
    }

    #[Test]
    public function アイテム系のファクトリはマスターidをそのまま使う(): void
    {
        $this->assertResource(Resource::item('item_001', 1), ResourceType::ITEM, 'item_001', 1);
        $this->assertResource(Resource::consumable('potion_001', 2), ResourceType::CONSUMABLE, 'potion_001', 2);
        $this->assertResource(Resource::material('material_001', 3), ResourceType::MATERIAL, 'material_001', 3);
        $this->assertResource(Resource::ticket('ticket_001', 4), ResourceType::TICKET, 'ticket_001', 4);
        $this->assertResource(Resource::gachaTicket('gacha_ticket_001', 5), ResourceType::GACHA_TICKET, 'gacha_ticket_001', 5);
    }

    #[Test]
    public function 装備系のファクトリ(): void
    {
        $this->assertResource(Resource::equipment('equipment_001', 1), ResourceType::EQUIPMENT, 'equipment_001', 1);
        $this->assertResource(Resource::weapon('weapon_001', 1), ResourceType::WEAPON, 'weapon_001', 1);
        $this->assertResource(Resource::armor('armor_001', 1), ResourceType::ARMOR, 'armor_001', 1);
        $this->assertResource(Resource::accessory('accessory_001', 1), ResourceType::ACCESSORY, 'accessory_001', 1);
    }

    #[Test]
    public function ユニットはグレードとレベルをmetadataに入れる(): void
    {
        $unit = Resource::unit('unit_001', 1, grade: 3, level: 10);

        $this->assertSame(['grade' => 3, 'level' => 10], $unit->getMetadata());

        // 片方だけの指定もできる
        $this->assertSame(['grade' => 2], Resource::unit('unit_001', 1, grade: 2)->getMetadata());
        $this->assertSame(['level' => 5], Resource::unit('unit_001', 1, level: 5)->getMetadata());

        // 未指定ならmetadataは持たない
        $this->assertNull(Resource::unit('unit_001', 1)->getMetadata());
    }

    #[Test]
    public function ポイント系のファクトリ(): void
    {
        $this->assertResource(Resource::alliancePoints(10), ResourceType::ALLIANCE_POINTS, 'alliance_points', 10);
        $this->assertResource(Resource::pvpPoints(20), ResourceType::PVP_POINTS, 'pvp_points', 20);
        $this->assertResource(Resource::eventPoints(30), ResourceType::EVENT_POINTS, 'event_points', 30);
        $this->assertResource(Resource::achievementPoints(40), ResourceType::ACHIEVEMENT_POINTS, 'achievement_points', 40);
        $this->assertResource(Resource::vipPoints(50), ResourceType::VIP_POINTS, 'vip_points', 50);
    }

    #[Test]
    public function カスタムリソースは任意のidとmetadataを持てる(): void
    {
        $custom = Resource::custom('event_medal', 5, ['event_id' => 'summer']);

        $this->assertResource($custom, ResourceType::CUSTOM, 'event_medal', 5);
        $this->assertSame(['event_id' => 'summer'], $custom->getMetadata());
    }

    #[Test]
    public function 配列から生成できる(): void
    {
        $resource = Resource::fromArray([
            'type' => 'item',
            'id' => 'item_001',
            'amount' => 3,
            'expire_at' => '2026-12-31 23:59:59',
            'metadata' => ['from' => 'mailbox'],
        ]);

        $this->assertResource($resource, ResourceType::ITEM, 'item_001', 3);
        $this->assertSame('2026-12-31 23:59:59', $resource->getExpireAt());
        $this->assertSame(['from' => 'mailbox'], $resource->getMetadata());
    }

    #[Test]
    public function 配列のtypeは列挙型でも受け付ける(): void
    {
        $resource = Resource::fromArray([
            'type' => ResourceType::UNIT,
            'id' => 'unit_001',
            'amount' => 1,
        ]);

        $this->assertSame(ResourceType::UNIT, $resource->getType());
        $this->assertNull($resource->getExpireAt());
        $this->assertNull($resource->getMetadata());
    }

    #[Test]
    public function 文字列のタイプから生成できる(): void
    {
        $resource = Resource::fromTypeString('gold', 'gold', 100);

        $this->assertResource($resource, ResourceType::GOLD, 'gold', 100);
    }

    #[Test]
    public function 未知のタイプ文字列は例外になる(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Invalid resource type: no_such_type');

        Resource::fromTypeString('no_such_type', 'id', 1);
    }

    #[Test]
    public function 配列に変換できる(): void
    {
        $resource = Resource::coin(100, '2026-12-31 23:59:59');
        $array = $resource->toArray();

        $this->assertSame($resource->getUniqueId(), $array['unique_id']);
        $this->assertSame('coin', $array['type']);
        $this->assertSame('coin', $array['id']);
        $this->assertSame(100, $array['amount']);
        $this->assertSame('2026-12-31 23:59:59', $array['expire_at']);
        $this->assertNull($array['metadata']);
    }

    #[Test]
    public function 配列との相互変換で内容が保たれる(): void
    {
        $original = Resource::unit('unit_001', 1, grade: 3, level: 10);
        $restored = Resource::fromArray($original->toArray());

        $this->assertSame($original->getTypeValue(), $restored->getTypeValue());
        $this->assertSame($original->getId(), $restored->getId());
        $this->assertSame($original->getAmount(), $restored->getAmount());
        $this->assertSame($original->getMetadata(), $restored->getMetadata());
    }

    private function assertResource(Resource $resource, ResourceType $type, string $id, int $amount): void
    {
        $this->assertSame($type, $resource->getType(), "{$id} のタイプが違う");
        $this->assertSame($id, $resource->getId());
        $this->assertSame($amount, $resource->getAmount());
    }
}
