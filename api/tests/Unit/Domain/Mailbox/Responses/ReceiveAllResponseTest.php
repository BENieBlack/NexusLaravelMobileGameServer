<?php

namespace Tests\Unit\Domain\Mailbox\Responses;

use App\Domain\Mailbox\Responses\ReceiveAllResponse;
use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliverySummary;
use Nexus\Core\Support\CustomCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ReceiveAllResponse のレスポンス生成テスト
 *
 * ResourceDeliveryService::deliver() が返す ResourceDeliverySummary を
 * そのまま受け取れること、および配列へ変換できることを検証する。
 */
class ReceiveAllResponseTest extends TestCase
{
    #[Test]
    public function test_delivery_contents_are_read_through_getters(): void
    {
        // Resource のプロパティは private なので、直接触ると実行時に落ちる
        $response = new ReceiveAllResponse(
            receivedMailboxIds: [1],
            totalCount: 1,
            skippedCount: 0,
            deliveryContents: [
                Resource::item('item_potion', 3),
                Resource::diamond(100),
            ],
        );

        $array = $response->toArray();

        $this->assertSame(
            [
                ['type' => 'item', 'id' => 'item_potion', 'amount' => 3],
                ['type' => 'diamond', 'id' => 'diamond', 'amount' => 100],
            ],
            $array['delivery_content_list'],
        );
    }

    #[Test]
    public function test_delivery_contents_are_omitted_when_empty(): void
    {
        $response = new ReceiveAllResponse(
            receivedMailboxIds: [],
            totalCount: 0,
            skippedCount: 0,
            deliveryContents: [],
        );

        $this->assertArrayNotHasKey('delivery_content_list', $response->toArray());
    }

    #[Test]
    public function test_accepts_delivery_summary_returned_by_delivery_service(): void
    {
        $summary = $this->makeSummary();

        $response = new ReceiveAllResponse(
            receivedMailboxIds: [1, 2],
            totalCount: 2,
            skippedCount: 0,
            deliveryContents: [],
            deliverySummary: $summary,
        );

        $array = $response->toArray();

        $this->assertSame([1, 2], $array['received_trx_mailbox_id_list']);
        $this->assertSame(2, $array['total_count']);
        // 成否は HTTP ステータスで伝えるので success は返さない
        $this->assertArrayNotHasKey('success', $array);
    }

    #[Test]
    public function test_delivery_summary_is_serialized(): void
    {
        $response = new ReceiveAllResponse(
            receivedMailboxIds: [1],
            totalCount: 1,
            skippedCount: 0,
            deliveryContents: [],
            deliverySummary: $this->makeSummary(),
        );

        $array = $response->toArray();

        $this->assertArrayHasKey('delivery_summary', $array);
        $this->assertSame(1, $array['delivery_summary']['total_count']);
        $this->assertCount(1, $array['delivery_summary']['result_list']);
        $this->assertArrayHasKey('resource', $array['delivery_summary']['result_list'][0]);
    }

    #[Test]
    public function test_delivery_summary_is_omitted_when_null(): void
    {
        $response = new ReceiveAllResponse(
            receivedMailboxIds: [],
            totalCount: 0,
            skippedCount: 3,
            deliveryContents: [],
            deliverySummary: null,
        );

        $array = $response->toArray();

        $this->assertArrayNotHasKey('delivery_summary', $array);
        $this->assertSame(0, $array['total_count']);
    }

    private function makeSummary(): ResourceDeliverySummary
    {
        $content = new ResourceDeliveryContent(
            new Resource(
                type: ResourceType::GOLD,
                id: 'gold',
                amount: 100,
            )
        );

        $summary = new ResourceDeliverySummary;
        $summary->addContents(new CustomCollection([$content]));

        return $summary;
    }
}
