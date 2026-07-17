<?php

namespace NexusGacha\Tests\Unit\Dto;

use NexusGacha\Dto\GachaPrizeDto;
use PHPUnit\Framework\TestCase;

class GachaPrizeDtoTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $dto = new GachaPrizeDto(
            contentType: 'Item',
            contentId: 'item_001',
            amount: 10,
            rarity: 3,
            isGuaranteed: false
        );

        $this->assertSame('Item', $dto->getContentType());
        $this->assertSame('item_001', $dto->getContentId());
        $this->assertSame(10, $dto->getAmount());
        $this->assertSame(3, $dto->getRarity());
        $this->assertFalse($dto->isGuaranteed());
    }

    public function test_is_guaranteed_returns_true_when_set(): void
    {
        $dto = new GachaPrizeDto(
            contentType: 'Unit',
            contentId: 'unit_ssr_001',
            amount: 1,
            rarity: 5,
            isGuaranteed: true
        );

        $this->assertTrue($dto->isGuaranteed());
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $dto = new GachaPrizeDto(
            contentType: 'Equipment',
            contentId: 'equip_001',
            amount: 1,
            rarity: 4,
            isGuaranteed: false
        );

        $expected = [
            'content_type' => 'Equipment',
            'content_id' => 'equip_001',
            'amount' => 1,
            'rarity' => 4,
            'is_guaranteed' => false,
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    public function test_to_array_includes_guaranteed_flag(): void
    {
        $dto = new GachaPrizeDto(
            contentType: 'Unit',
            contentId: 'unit_001',
            amount: 1,
            rarity: 5,
            isGuaranteed: true
        );

        $array = $dto->toArray();

        $this->assertTrue($array['is_guaranteed']);
    }
}
