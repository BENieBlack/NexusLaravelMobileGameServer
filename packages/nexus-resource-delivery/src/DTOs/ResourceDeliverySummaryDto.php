<?php

namespace NexusResourceDelivery\DTOs;

use NexusResourceDelivery\Enums\ResourceDeliveryResultReason;
use Illuminate\Support\Collection;

/**
 * ResourceDeliverySummary
 *
 * リソース配送結果についてまとめるDTO
 */
class ResourceDeliverySummaryDto
{
    /**
     * @var Collection<ResourceDeliveryContent>
     */
    private Collection $contents;

    public function __construct()
    {
        $this->contents = collect();
    }

    /**
     * 配送済みコンテンツのリストを取得
     *
     * @return Collection<ResourceDeliveryContent>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }

    /**
     * 配送済みコンテンツを追加
     *
     * @param Collection<ResourceDeliveryContent> $contents
     */
    public function addContents(Collection $contents): void
    {
        // 要素を上書きまたは削除をしないようにvaluesしてからmergeする
        $this->contents = $this->contents->values()
            ->merge($contents->values());
    }

    /**
     * 別のサマリーをマージ
     *
     * @param ResourceDeliverySummaryDto $summary
     */
    public function merge(ResourceDeliverySummaryDto $summary): void
    {
        $this->addContents($summary->getContents());
    }

    /**
     * 配送済みコンテンツの総数を取得
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->contents->count();
    }

    /**
     * 対象のコンテンツの内で、所持上限を超えたリソースがあるかどうか
     *
     * @param array<string> $resourceTypes チェック対象のリソースタイプ
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
