<?php

namespace App\Domain\Delivery\DTOs;

use App\Domain\Delivery\DTOs\DeliveryContent;

/**
 * DeliveryResult
 *
 * 配送処理の結果を表現するDTO
 */
readonly class DeliveryResult
{
    /**
     * @param array<DeliveryContent> $deliveredItemArray 配送成功したコンテンツ
     * @param array<array{item: DeliveryContent, error: string}> $failedItemArray 配送失敗したコンテンツとエラー情報
     * @param int $totalCount 総配送試行数
     * @param int $successCount 成功数
     * @param int $failedCount 失敗数
     */
    public function __construct(
        public array $deliveredItemArray,
        public array $failedItemArray,
        public int   $totalCount,
        public int   $successCount,
        public int   $failedCount,
    ) {
    }

    /**
     * すべて成功したかどうか
     *
     * @return bool
     */
    public function isAllSuccess(): bool
    {
        return $this->failedCount === 0;
    }

    /**
     * 配列に変換
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'delivered_items' => array_map(fn($item) => $item->toArray(), $this->deliveredItemArray),
            'failed_items' => array_map(function ($failed) {
                return [
                    'item' => $failed['item']->toArray(),
                    'error' => $failed['error'],
                ];
            }, $this->failedItemArray),
            'total_count' => $this->totalCount,
            'success_count' => $this->successCount,
            'failed_count' => $this->failedCount,
        ];
    }

    /**
     * 成功結果を作成
     *
     * @param array<DeliveryContent> $itemArray
     * @return self
     */
    public static function success(array $itemArray): self
    {
        return new self(
            deliveredItemArray: $itemArray,
            failedItemArray: [],
            totalCount: count($itemArray),
            successCount: count($itemArray),
            failedCount: 0,
        );
    }

    /**
     * 部分的成功の結果を作成
     *
     * @param array<DeliveryContent> $deliveredArray
     * @param array<array{item: DeliveryContent, error: string}> $failedArray
     * @return self
     */
    public static function partial(array $deliveredArray, array $failedArray): self
    {
        return new self(
            deliveredItemArray: $deliveredArray,
            failedItemArray: $failedArray,
            totalCount: count($deliveredArray) + count($failedArray),
            successCount: count($deliveredArray),
            failedCount: count($failedArray),
        );
    }
}
