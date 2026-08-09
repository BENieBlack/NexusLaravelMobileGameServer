<?php

namespace NexusResourceDelivery\Tests\Unit\DTOs;

use NexusResourceDelivery\DTOs\ResourceDeliveryContent;
use NexusResourceDelivery\DTOs\ResourceDeliveryResultDto;
use PHPUnit\Framework\TestCase;

class ResourceDeliveryResultDtoTest extends TestCase
{
    private function createMockContent(string $type, string $id, int $amount): ResourceDeliveryContent
    {
        return new ResourceDeliveryContent(
            type: $type,
            id: $id,
            amount: $amount
        );
    }

    public function test_is_all_success_returns_true_when_no_failures(): void
    {
        $result = new ResourceDeliveryResultDto(
            deliveredItemArray: [$this->createMockContent('currency', 'gold', 100)],
            failedItemArray: [],
            totalCount: 1,
            successCount: 1,
            failedCount: 0
        );

        $this->assertTrue($result->isAllSuccess());
    }

    public function test_is_all_success_returns_false_when_has_failures(): void
    {
        $result = new ResourceDeliveryResultDto(
            deliveredItemArray: [$this->createMockContent('currency', 'gold', 100)],
            failedItemArray: [
                ['item' => $this->createMockContent('item', 'item_001', 10), 'error' => 'Test error'],
            ],
            totalCount: 2,
            successCount: 1,
            failedCount: 1
        );

        $this->assertFalse($result->isAllSuccess());
    }

    public function test_success_creates_successful_result(): void
    {
        $items = [
            $this->createMockContent('currency', 'gold', 100),
            $this->createMockContent('item', 'item_001', 5),
        ];

        $result = ResourceDeliveryResultDto::success($items);

        $this->assertTrue($result->isAllSuccess());
        $array = $result->toArray();
        $this->assertEquals(2, $array['total_count']);
        $this->assertEquals(2, $array['success_count']);
        $this->assertEquals(0, $array['failed_count']);
        $this->assertCount(2, $array['delivered_items']);
        $this->assertCount(0, $array['failed_items']);
    }

    public function test_partial_creates_partial_success_result(): void
    {
        $deliveredItems = [
            $this->createMockContent('currency', 'gold', 100),
        ];

        $failedItems = [
            ['item' => $this->createMockContent('item', 'item_001', 5), 'error' => 'Inventory full'],
        ];

        $result = ResourceDeliveryResultDto::partial($deliveredItems, $failedItems);

        $this->assertFalse($result->isAllSuccess());
        $array = $result->toArray();
        $this->assertEquals(2, $array['total_count']);
        $this->assertEquals(1, $array['success_count']);
        $this->assertEquals(1, $array['failed_count']);
        $this->assertCount(1, $array['delivered_items']);
        $this->assertCount(1, $array['failed_items']);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $deliveredItems = [
            $this->createMockContent('currency', 'gold', 100),
        ];

        $failedItems = [
            ['item' => $this->createMockContent('item', 'item_001', 5), 'error' => 'Test error'],
        ];

        $result = new ResourceDeliveryResultDto(
            deliveredItemArray: $deliveredItems,
            failedItemArray: $failedItems,
            totalCount: 2,
            successCount: 1,
            failedCount: 1
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('delivered_items', $array);
        $this->assertArrayHasKey('failed_items', $array);
        $this->assertArrayHasKey('total_count', $array);
        $this->assertArrayHasKey('success_count', $array);
        $this->assertArrayHasKey('failed_count', $array);

        $this->assertCount(1, $array['delivered_items']);
        $this->assertCount(1, $array['failed_items']);
        $this->assertEquals(2, $array['total_count']);
        $this->assertEquals(1, $array['success_count']);
        $this->assertEquals(1, $array['failed_count']);

        // Failed item structure
        $this->assertArrayHasKey('item', $array['failed_items'][0]);
        $this->assertArrayHasKey('error', $array['failed_items'][0]);
        $this->assertEquals('Test error', $array['failed_items'][0]['error']);
    }

    public function test_to_array_with_empty_result(): void
    {
        $result = new ResourceDeliveryResultDto(
            deliveredItemArray: [],
            failedItemArray: [],
            totalCount: 0,
            successCount: 0,
            failedCount: 0
        );

        $array = $result->toArray();

        $this->assertEquals(0, $array['total_count']);
        $this->assertEquals(0, $array['success_count']);
        $this->assertEquals(0, $array['failed_count']);
        $this->assertCount(0, $array['delivered_items']);
        $this->assertCount(0, $array['failed_items']);
        $this->assertTrue($result->isAllSuccess());
    }

    public function test_success_with_empty_array(): void
    {
        $result = ResourceDeliveryResultDto::success([]);

        $this->assertTrue($result->isAllSuccess());
        $array = $result->toArray();
        $this->assertEquals(0, $array['total_count']);
        $this->assertEquals(0, $array['success_count']);
        $this->assertEquals(0, $array['failed_count']);
    }

    public function test_partial_calculates_counts_correctly(): void
    {
        $deliveredItems = [
            $this->createMockContent('currency', 'gold', 100),
            $this->createMockContent('currency', 'silver', 200),
            $this->createMockContent('item', 'item_001', 1),
        ];

        $failedItems = [
            ['item' => $this->createMockContent('item', 'item_002', 5), 'error' => 'Error 1'],
            ['item' => $this->createMockContent('item', 'item_003', 3), 'error' => 'Error 2'],
        ];

        $result = ResourceDeliveryResultDto::partial($deliveredItems, $failedItems);

        $array = $result->toArray();
        $this->assertEquals(5, $array['total_count']);
        $this->assertEquals(3, $array['success_count']);
        $this->assertEquals(2, $array['failed_count']);
    }
}
