<?php

namespace NexusResourceDelivery\ValueObjects;

use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * リソース配送結果 Value Object
 *
 * 配送処理の成否と内訳を保持する不変オブジェクト。
 *
 * 件数は配送済み・失敗の配列から導出するため、
 * 配列の中身と件数がずれることはない。
 */
final class ResourceDeliveryResult
{
    /**
     * @param  array<ResourceDeliveryContent>  $deliveredItemArray  配送成功したコンテンツ
     * @param  array<array{item: ResourceDeliveryContent, error: string}>  $failedItemArray  配送失敗したコンテンツとエラー情報
     */
    public function __construct(
        private readonly array $deliveredItemArray,
        private readonly array $failedItemArray,
    ) {}

    /**
     * 成功結果を作成
     *
     * @param  array<ResourceDeliveryContent>  $itemArray
     */
    public static function success(array $itemArray): self
    {
        return new self($itemArray, []);
    }

    /**
     * 部分的成功の結果を作成
     *
     * @param  array<ResourceDeliveryContent>  $deliveredArray
     * @param  array<array{item: ResourceDeliveryContent, error: string}>  $failedArray
     */
    public static function partial(array $deliveredArray, array $failedArray): self
    {
        return new self($deliveredArray, $failedArray);
    }

    /**
     * 配送成功したコンテンツを取得
     *
     * @return array<ResourceDeliveryContent>
     */
    public function getDeliveredItemArray(): array
    {
        return $this->deliveredItemArray;
    }

    /**
     * 配送失敗したコンテンツを取得
     *
     * @return array<array{item: ResourceDeliveryContent, error: string}>
     */
    public function getFailedItemArray(): array
    {
        return $this->failedItemArray;
    }

    /**
     * 総配送試行数を取得
     */
    public function getTotalCount(): int
    {
        return $this->getSuccessCount() + $this->getFailedCount();
    }

    /**
     * 成功数を取得
     */
    public function getSuccessCount(): int
    {
        return count($this->deliveredItemArray);
    }

    /**
     * 失敗数を取得
     */
    public function getFailedCount(): int
    {
        return count($this->failedItemArray);
    }

    /**
     * すべて成功したかどうか
     */
    public function isAllSuccess(): bool
    {
        return $this->getFailedCount() === 0;
    }

    /**
     * 配送対象が無かったか
     */
    public function isEmpty(): bool
    {
        return $this->getTotalCount() === 0;
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'delivered_items' => array_map(fn ($item) => $item->toArray(), $this->deliveredItemArray),
            'failed_items' => array_map(function ($failed) {
                return [
                    'item' => $failed['item']->toArray(),
                    'error' => $failed['error'],
                ];
            }, $this->failedItemArray),
            'total_count' => $this->getTotalCount(),
            'success_count' => $this->getSuccessCount(),
            'failed_count' => $this->getFailedCount(),
        ];
    }
}
