<?php

namespace App\Http\Responses\Mailbox;

use App\Http\Responses\_BaseResponse;

/**
 * ReceiveAllResponse
 *
 * 一括受取レスポンス
 */
class ReceiveAllResponse extends _BaseResponse
{
    /**
     * @param  array<int>  $receivedMailboxIds  受取完了したメールID配列
     * @param  int  $totalCount  受取完了したメール数
     * @param  int  $skippedCount  スキップされたメール数
     * @param  array  $deliveryContents  配送されたアイテム情報
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
            'received_mailbox_ids' => $this->receivedMailboxIds,
            'total_count' => $this->totalCount,
            'skipped_count' => $this->skippedCount,
            'success' => $this->totalCount > 0,
        ];

        // 配送内容を追加
        if (count($this->deliveryContents) > 0) {
            $response['delivery_contents'] = array_map(function ($content) {
                return [
                    'type' => $content->type,
                    'id' => $content->id,
                    'amount' => $content->amount,
                ];
            }, $this->deliveryContents);
        }

        // 配送サマリーを追加
        if ($this->deliverySummary !== null) {
            $response['delivery_summary'] = [
                'total_count' => $this->deliverySummary->getTotalCount(),
                'results' => $this->deliverySummary->getResults(),
            ];
        }

        return $response;
    }
}
