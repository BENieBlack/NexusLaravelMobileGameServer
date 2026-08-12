<?php

namespace NexusResourceDelivery\Tests\Unit\ValueObjects;

use NexusResource\DTOs\ResourceDto;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;
use NexusResourceDelivery\ValueObjects\ResourceDeliveryResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceDeliveryResultTest extends TestCase
{
    private function makeContent(string $id = 'item_001', int $amount = 1): ResourceDeliveryContentDto
    {
        return new ResourceDeliveryContentDto(
            new ResourceDto(ResourceType::ITEM, $id, $amount)
        );
    }

    #[Test]
    public function test_success_marks_all_as_delivered()
    {
        $items = [$this->makeContent('item_001'), $this->makeContent('item_002')];

        $result = ResourceDeliveryResult::success($items);

        $this->assertTrue($result->isAllSuccess());
        $this->assertSame(2, $result->getTotalCount());
        $this->assertSame(2, $result->getSuccessCount());
        $this->assertSame(0, $result->getFailedCount());
        $this->assertCount(2, $result->getDeliveredItemArray());
        $this->assertSame([], $result->getFailedItemArray());
    }

    #[Test]
    public function test_partial_calculates_counts_from_arrays()
    {
        $delivered = [$this->makeContent('item_001')];
        $failed = [
            ['item' => $this->makeContent('item_002'), 'error' => '上限超過'],
            ['item' => $this->makeContent('item_003'), 'error' => '配布不可'],
        ];

        $result = ResourceDeliveryResult::partial($delivered, $failed);

        $this->assertFalse($result->isAllSuccess());
        $this->assertSame(3, $result->getTotalCount());
        $this->assertSame(1, $result->getSuccessCount());
        $this->assertSame(2, $result->getFailedCount());
    }

    #[Test]
    public function test_counts_are_derived_and_cannot_drift()
    {
        // 配列を渡すだけで件数が決まるため、内訳と件数がずれない
        $result = new ResourceDeliveryResult(
            [$this->makeContent('a'), $this->makeContent('b')],
            [['item' => $this->makeContent('c'), 'error' => 'e']],
        );

        $this->assertSame(2, $result->getSuccessCount());
        $this->assertSame(1, $result->getFailedCount());
        $this->assertSame(3, $result->getTotalCount());
    }

    #[Test]
    public function test_empty_result()
    {
        $result = ResourceDeliveryResult::success([]);

        $this->assertTrue($result->isAllSuccess());
        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->getTotalCount());
    }

    #[Test]
    public function test_to_array_returns_correct_structure()
    {
        $delivered = [$this->makeContent('item_001')];
        $failed = [['item' => $this->makeContent('item_002'), 'error' => '上限超過']];

        $array = ResourceDeliveryResult::partial($delivered, $failed)->toArray();

        $this->assertArrayHasKey('delivered_items', $array);
        $this->assertArrayHasKey('failed_items', $array);
        $this->assertSame(2, $array['total_count']);
        $this->assertSame(1, $array['success_count']);
        $this->assertSame(1, $array['failed_count']);
        $this->assertSame('上限超過', $array['failed_items'][0]['error']);
    }

    #[Test]
    public function test_to_array_with_empty_result()
    {
        $array = ResourceDeliveryResult::success([])->toArray();

        $this->assertSame([], $array['delivered_items']);
        $this->assertSame([], $array['failed_items']);
        $this->assertSame(0, $array['total_count']);
    }
}
