<?php

namespace NexusResource\Tests\Unit\DataTransferObjects;

use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use PHPUnit\Framework\TestCase;

class ResourceDtoTest extends TestCase
{
    public function test_constructor_creates_resource_with_basic_properties(): void
    {
        $resource = new Resource(
            type: ResourceType::GOLD,
            id: 'gold',
            amount: 100
        );

        $this->assertEquals(ResourceType::GOLD, $resource->getType());
        $this->assertEquals('gold', $resource->getTypeValue());
        $this->assertEquals('gold', $resource->getId());
        $this->assertEquals(100, $resource->getAmount());
        $this->assertNull($resource->getExpireAt());
        $this->assertNull($resource->getMetadata());
    }

    public function test_constructor_creates_resource_with_expire_at(): void
    {
        $expireAt = '2026-12-31 23:59:59';
        
        $resource = new Resource(
            type: ResourceType::GOLD,
            id: 'gold',
            amount: 100,
            expireAt: $expireAt
        );

        $this->assertEquals($expireAt, $resource->getExpireAt());
    }

    public function test_constructor_creates_resource_with_metadata(): void
    {
        $metadata = ['is_paid' => true, 'campaign_id' => 'summer2024'];
        
        $resource = new Resource(
            type: ResourceType::GOLD,
            id: 'gold',
            amount: 100,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $resource->getMetadata());
    }

    public function test_set_amount_updates_amount(): void
    {
        $resource = new Resource(
            type: ResourceType::ITEM,
            id: 'item_potion_001',
            amount: 10
        );

        $resource->setAmount(50);
        
        $this->assertEquals(50, $resource->getAmount());
    }

    public function test_unique_id_is_generated(): void
    {
        $resource1 = new Resource(
            type: ResourceType::GOLD,
            id: 'gold',
            amount: 100
        );

        $resource2 = new Resource(
            type: ResourceType::GOLD,
            id: 'gold',
            amount: 100
        );

        $this->assertNotEmpty($resource1->getUniqueId());
        $this->assertNotEmpty($resource2->getUniqueId());
        $this->assertNotEquals($resource1->getUniqueId(), $resource2->getUniqueId());
    }

    public function test_from_type_string_creates_resource_from_gold_type(): void
    {
        $resource = Resource::fromTypeString('gold', 'gold', 500);

        $this->assertEquals(ResourceType::GOLD, $resource->getType());
        $this->assertEquals('gold', $resource->getId());
        $this->assertEquals(500, $resource->getAmount());
    }

    public function test_from_type_string_creates_resource_from_item_type(): void
    {
        $resource = Resource::fromTypeString('item', 'item_potion_001', 10);

        $this->assertEquals(ResourceType::ITEM, $resource->getType());
        $this->assertEquals('item_potion_001', $resource->getId());
        $this->assertEquals(10, $resource->getAmount());
    }

    public function test_from_type_string_creates_resource_from_unit_type(): void
    {
        $metadata = ['grade' => 1, 'level' => 1];
        $resource = Resource::fromTypeString('unit', 'unit_hero_001', 1, null, $metadata);

        $this->assertEquals(ResourceType::UNIT, $resource->getType());
        $this->assertEquals('unit_hero_001', $resource->getId());
        $this->assertEquals(1, $resource->getAmount());
        $this->assertEquals($metadata, $resource->getMetadata());
    }

    public function test_from_type_string_creates_resource_from_equipment_type(): void
    {
        $metadata = ['level' => 1];
        $resource = Resource::fromTypeString('equipment', 'equipment_sword_001', 1, null, $metadata);

        $this->assertEquals(ResourceType::EQUIPMENT, $resource->getType());
        $this->assertEquals('equipment_sword_001', $resource->getId());
        $this->assertEquals(1, $resource->getAmount());
        $this->assertEquals($metadata, $resource->getMetadata());
    }

    public function test_from_type_string_creates_resource_from_diamond_type(): void
    {
        $resource = Resource::fromTypeString('diamond', 'paid', 1000, null, ['is_paid' => true]);

        $this->assertEquals(ResourceType::DIAMOND, $resource->getType());
        $this->assertEquals('paid', $resource->getId());
        $this->assertEquals(1000, $resource->getAmount());
    }

    public function test_from_type_string_throws_exception_for_invalid_type(): void
    {
        $this->expectException(\ValueError::class);
        
        Resource::fromTypeString('invalid_type', 'some_id', 100);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $metadata = ['is_paid' => true, 'campaign_id' => 'summer2024'];
        $expireAt = '2026-12-31 23:59:59';
        
        $resource = new Resource(
            type: ResourceType::GOLD,
            id: 'gold',
            amount: 100,
            expireAt: $expireAt,
            metadata: $metadata
        );

        $array = $resource->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('gold', $array['type']);
        $this->assertEquals('gold', $array['id']);
        $this->assertEquals(100, $array['amount']);
        $this->assertEquals($expireAt, $array['expire_at']);
        $this->assertEquals($metadata, $array['metadata']);
    }

    public function test_to_array_with_minimal_properties(): void
    {
        $resource = new Resource(
            type: ResourceType::ITEM,
            id: 'item_potion_001',
            amount: 10
        );

        $array = $resource->toArray();

        $this->assertEquals('item', $array['type']);
        $this->assertEquals('item_potion_001', $array['id']);
        $this->assertEquals(10, $array['amount']);
        $this->assertNull($array['expire_at']);
        $this->assertNull($array['metadata']);
    }
}
