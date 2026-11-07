<?php

namespace App\Domain\Mailbox\Responses;

use App\Domain\_BaseResponse;
use App\Traits\HasUpdatedResources;
use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliverySummary;

/**
 * ReceiveAllResponse
 *
 * 一括受取レスポンス
 */
class ReceiveAllResponse extends _BaseResponse
{
    use HasUpdatedResources;

    /**
     * @param  array<int>  $receivedMailboxIds  受取完了したメールID配列
     * @param  int  $totalCount  受取完了したメール数
     * @param  int  $skippedCount  スキップされたメール数
     * @param  array<int, \Nexus\Core\Resource\DataTransferObjects\Resource>  $deliveryContents  配送されたアイテム情報
     * @param  ResourceDeliverySummary|null  $deliverySummary  配送サマリー
     */
    public function __construct(
        private array $receivedMailboxIds,
        private int $totalCount,
        private int $skippedCount,
        private array $deliveryContents,
        private ?ResourceDeliverySummary $deliverySummary = null,
    ) {}

    /**
     * レスポンス配列を取得
     */
    public function toArray(): array
    {
        $response = [
            'received_trx_mailbox_id_list' => $this->receivedMailboxIds,
            'total_count' => $this->totalCount,
            'skipped_count' => $this->skippedCount,
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];

        // 配送内容を追加
        if (count($this->deliveryContents) > 0) {
            $response['delivery_content_list'] = array_map(function (Resource $content) {
                return [
                    'type' => $content->getTypeValue(),
                    'id' => $content->getId(),
                    'amount' => $content->getAmount(),
                ];
            }, $this->deliveryContents);
        }

        // 配送サマリーを追加
        if ($this->deliverySummary !== null) {
            $response['delivery_summary'] = [
                'total_count' => $this->deliverySummary->getTotalCount(),
                'result_list' => $this->deliverySummary->getContents()
                    ->map(fn (ResourceDeliveryContent $content) => $content->toArray())
                    ->values()
                    ->all(),
            ];
        }

        return $response;
    }
}
