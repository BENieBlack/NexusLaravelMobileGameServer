<?php

namespace App\Domain\Delivery\DTOs;

use App\Domain\Delivery\Enums\DeliveryResultReason;
use Illuminate\Support\Collection;

/**
 * DeliverySummary
 *
 * 配送結果についてまとめるDTO
 */
class DeliverySummary
{
    /**
     * @var Collection<DeliveryContent>
     */
    private Collection $contents;

    public function __construct()
    {
        $this->contents = collect();
    }

    /**
     * 配送済みコンテンツのリストを取得
     *
     * @return Collection<DeliveryContent>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }

    /**
     * 配送済みコンテンツを追加
     *
     * @param Collection<DeliveryContent> $contents
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
     * @param DeliverySummary $summary
     */
    public function merge(DeliverySummary $summary): void
    {
        $this->addContents($summary->getContents());
    }

    /**
     * 対象のコンテンツの内で、所持上限を超えたリソースがあるかどうか
     *
     * @param array<string> $contentTypes チェック対象のリソースタイプ
     * @return bool true: 所持上限を超えたリソースがある, false: 所持上限を超えたリソースはない
     */
    public function hasResourceOverflow(array $contentTypes): bool
    {
        if (count($contentTypes) === 0) {
            return false;
        }
        $contentTypes = array_fill_keys($contentTypes, true);

        foreach ($this->contents as $content) {
            if (isset($contentTypes[$content->getType()]) === false) {
                continue; // チェック対象外のリソースタイプなのでスキップ
            }

            // 上限到達やインベントリ満杯の場合
            if ($content->getFailureReason() === DeliveryResultReason::RESOURCE_LIMIT_REACHED ||
                $content->getFailureReason() === DeliveryResultReason::INVENTORY_FULL) {
                return true;
            }
        }

        return false;
    }
}
