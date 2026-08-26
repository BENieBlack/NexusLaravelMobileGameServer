<?php

namespace NexusResource\Tests\Unit\Enums;

use NexusResource\Enums\ResourceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ResourceType のユニットテスト
 *
 * 配送Handlerの振り分けが分類メソッドに依存するため、
 * どのタイプがどの分類に入るかを固定する。
 * 値そのものもマスタや配送コンテンツに載るので変えられない。
 */
class ResourceTypeTest extends TestCase
{
    #[Test]
    public function 通貨タイプの分類(): void
    {
        foreach ([ResourceType::DIAMOND, ResourceType::PAID_DIAMOND, ResourceType::GOLD, ResourceType::COIN] as $type) {
            $this->assertTrue($type->isCurrency(), "{$type->value} は通貨のはず");
        }

        $this->assertFalse(ResourceType::FOOD->isCurrency());
        $this->assertFalse(ResourceType::ITEM->isCurrency());
    }

    #[Test]
    public function 自然資源にスタミナと経験値は含まない(): void
    {
        foreach ([ResourceType::FOOD, ResourceType::WOOD, ResourceType::STONE, ResourceType::IRON] as $type) {
            $this->assertTrue($type->isNaturalResource(), "{$type->value} は自然資源のはず");
        }

        // 管理方法が違うので別扱い（専用Handlerが担当する）
        $this->assertFalse(ResourceType::STAMINA->isNaturalResource());
        $this->assertFalse(ResourceType::EXPERIENCE->isNaturalResource());
    }

    #[Test]
    public function アイテムタイプの分類(): void
    {
        foreach ([ResourceType::ITEM, ResourceType::CONSUMABLE, ResourceType::MATERIAL, ResourceType::TICKET] as $type) {
            $this->assertTrue($type->isItem(), "{$type->value} はアイテムのはず");
        }

        // ガチャチケットはアイテム分類に入れていない
        $this->assertFalse(ResourceType::GACHA_TICKET->isItem());
        $this->assertFalse(ResourceType::UNIT->isItem());
    }

    #[Test]
    public function 装備タイプの分類(): void
    {
        foreach ([ResourceType::EQUIPMENT, ResourceType::WEAPON, ResourceType::ARMOR, ResourceType::ACCESSORY] as $type) {
            $this->assertTrue($type->isEquipment(), "{$type->value} は装備のはず");
        }

        $this->assertFalse(ResourceType::UNIT->isEquipment());
    }

    #[Test]
    public function ポイントタイプの分類(): void
    {
        $points = [
            ResourceType::ALLIANCE_POINTS,
            ResourceType::PVP_POINTS,
            ResourceType::EVENT_POINTS,
            ResourceType::ACHIEVEMENT_POINTS,
            ResourceType::VIP_POINTS,
        ];

        foreach ($points as $type) {
            $this->assertTrue($type->isPoints(), "{$type->value} はポイントのはず");
        }

        $this->assertFalse(ResourceType::GOLD->isPoints());
    }

    #[Test]
    public function 全タイプにラベルとアイコンがある(): void
    {
        foreach (ResourceType::cases() as $type) {
            $this->assertNotSame('', $type->label(), "{$type->value} のラベルが空");
            $this->assertNotSame('', $type->icon(), "{$type->value} のアイコンが空");
        }
    }

    #[Test]
    public function 文字列から変換できる(): void
    {
        $this->assertSame(ResourceType::DIAMOND, ResourceType::fromString('diamond'));
        $this->assertSame(ResourceType::PVP_POINTS, ResourceType::fromString('pvp_points'));

        // 未知の値はnull（例外にしない）
        $this->assertNull(ResourceType::fromString('no_such_type'));
        $this->assertNull(ResourceType::fromString(''));
    }

    #[Test]
    public function 全タイプの値を一覧で取れる(): void
    {
        $all = ResourceType::all();

        $this->assertContains('diamond', $all);
        $this->assertContains('custom', $all);
        $this->assertCount(count(ResourceType::cases()), $all);
    }

    #[Test]
    public function どの分類にも属さないタイプがある(): void
    {
        // customは汎用の受け皿で、配送Handlerも個別に用意していない
        $custom = ResourceType::CUSTOM;

        $this->assertFalse($custom->isCurrency());
        $this->assertFalse($custom->isNaturalResource());
        $this->assertFalse($custom->isItem());
        $this->assertFalse($custom->isEquipment());
        $this->assertFalse($custom->isPoints());
    }
}
