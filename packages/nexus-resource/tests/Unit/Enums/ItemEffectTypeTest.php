<?php

namespace NexusResource\Tests\Unit\Enums;

use NexusResource\Enums\ItemEffectType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ItemEffectType のユニットテスト
 *
 * mst_item.effect の値と1対1で対応する。マスタの文字列を変えると
 * 既存データの効果が失われるため、値そのものを固定する。
 */
class ItemEffectTypeTest extends TestCase
{
    #[Test]
    public function マスタに入る文字列と対応する(): void
    {
        $this->assertSame('player_exp', ItemEffectType::PLAYER_EXP->value);
        $this->assertSame('unit_exp', ItemEffectType::UNIT_EXP->value);
        $this->assertSame('equipment_exp', ItemEffectType::EQUIPMENT_EXP->value);
        $this->assertSame('stamina_recover', ItemEffectType::STAMINA_RECOVER->value);
    }

    #[Test]
    public function ユニットと装備の経験値は対象の指定が要る(): void
    {
        $this->assertTrue(ItemEffectType::UNIT_EXP->requiresTarget());
        $this->assertTrue(ItemEffectType::EQUIPMENT_EXP->requiresTarget());

        $this->assertFalse(ItemEffectType::PLAYER_EXP->requiresTarget());
        $this->assertFalse(ItemEffectType::STAMINA_RECOVER->requiresTarget());
    }

    #[Test]
    public function 未知の効果や未設定はnullになる(): void
    {
        $this->assertSame(ItemEffectType::UNIT_EXP, ItemEffectType::tryFromEffect('unit_exp'));
        $this->assertNull(ItemEffectType::tryFromEffect('restore_hp'));
        $this->assertNull(ItemEffectType::tryFromEffect(''));
        $this->assertNull(ItemEffectType::tryFromEffect(null));
    }
}
