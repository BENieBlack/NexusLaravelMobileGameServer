<?php

namespace App\Domain\Mailbox\DataTransferObjects;

use NexusResourceDelivery\DataTransferObjects\ResourceDeliverySummary;

/**
 * ReceiveAllResult
 *
 * 添付配布物一括受取の結果
 */
class ReceiveAllResult
{
    /**
     * @param  array<int>  $receivedMailboxIds  受取完了したメールID配列
     * @param  int  $totalCount  受取完了したメール数
     * @param  int  $skippedCount  スキップされたメール数
     * @param  array<int, \NexusResource\DataTransferObjects\Resource>  $deliveryContents  配送されたアイテム情報
     * @param  ResourceDeliverySummary|null  $deliverySummary  配送サマリー
     */
    public function __construct(
        public readonly array $receivedMailboxIds,
        public readonly int $totalCount,
        public readonly int $skippedCount,
        public readonly array $deliveryContents,
        public readonly ?ResourceDeliverySummary $deliverySummary = null,
    ) {}
}
