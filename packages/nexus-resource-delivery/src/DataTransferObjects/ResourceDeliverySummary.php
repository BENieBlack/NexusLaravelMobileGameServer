<?php

namespace NexusResourceDelivery\DataTransferObjects;

use Nexus\Core\Support\CustomCollection;
use NexusResourceDelivery\Enums\ResourceDeliveryResultReason;

/**
 * ResourceDeliverySummary
 *
 * リソース配送結果についてまとめるDTO
 */
class ResourceDeliverySummary
{
    /**
     * @var CustomCollection<array-key, ResourceDeliveryContent>
     */
    private CustomCollection $contents;

    public function __construct()
    {
        $this->contents = new CustomCollection;
    }

    /**
     * 配送済みコンテンツのリストを取得
     *
     * @return CustomCollection<array-key, ResourceDeliveryContent>
     */
    public function getContents(): CustomCollection
    {
        return $this->contents;
    }

    /**
     * 配送済みコンテンツを追加
     *
     * @param  CustomCollection<array-key, ResourceDeliveryContent>  $contents
     */
    public function addContents(CustomCollection $contents): void
    {
        // 要素を上書きまたは削除をしないようにvaluesしてからmergeする
        $this->contents = $this->contents->values()
            ->merge($contents->values());
    }

    /**
     * 別のサマリーをマージ
     */
    public function merge(ResourceDeliverySummary $summary): void
    {
        $this->addContents($summary->getContents());
    }

    /**
     * 配送済みコンテンツの総数を取得
     */
    public function getTotalCount(): int
    {
        return $this->contents->count();
    }

    /**
     * 対象のコンテンツの内で、所持上限を超えたリソースがあるかどうか
     *
     * @param  array<string>  $resourceTypes  チェック対象のリソースタイプ
     * @return bool true: 所持上限を超えたリソースがある, false: 所持上限を超えたリソースはない
     */
    public function hasResourceOverflow(array $resourceTypes): bool
    {
        if (count($resourceTypes) === 0) {
            return false;
        }
        $resourceTypesMap = array_fill_keys($resourceTypes, true);

        foreach ($this->contents as $content) {
            if (isset($resourceTypesMap[$content->getTypeValue()]) === false) {
                continue; // チェック対象外のリソースタイプなのでスキップ
            }

            // 上限到達やインベントリ満杯の場合
            if ($content->getFailureReason() === ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED ||
                $content->getFailureReason() === ResourceDeliveryResultReason::INVENTORY_FULL) {
                return true;
            }
        }

        return false;
    }
}
