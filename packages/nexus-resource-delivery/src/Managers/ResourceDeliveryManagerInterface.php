<?php

namespace NexusResourceDelivery\Managers;

use NexusResourceDelivery\DTOs\ResourceDeliveryContent;
use NexusResourceDelivery\DTOs\ResourceDeliveryComplete;
use Illuminate\Support\Collection;

/**
 * ResourceDeliveryManagerInterface
 *
 * ResourceDeliveryManagerの最低限必要なメソッドを定義するインターフェース
 * 循環参照を避けるために使用
 */
interface ResourceDeliveryManagerInterface
{
    /**
     * 配送コンテンツを配送前リストに追加する
     */
    public function addContent(ResourceDeliveryContent $content): void;

    /**
     * 複数の配送コンテンツを配送前リストにまとめて追加する
     */
    public function addContents(Collection $contents): void;

    /**
     * 配送前リストからコンテンツを取得する
     *
     * @return Collection<string, ResourceDeliveryContent>
     */
    public function getPendingContents(): Collection;

    /**
     * 送信完了リストからコンテンツを取得する
     *
     * @param string $contentClass コンテンツクラス名
     * @return Collection<ResourceDeliveryContent>
     */
    public function getSendCompleteContents(string $contentClass): Collection;

    /**
     * 配送が必要なコンテンツがあるかチェック
     */
    public function hasPendingContents(): bool;

    /**
     * 配送処理を実行した後に実行する処理
     *
     * @param ResourceDeliveryComplete $sendCompleteData 送信完了データ
     * @return void
     */
    public function afterSend(ResourceDeliveryComplete $sendCompleteData): void;
}
