<?php

namespace NexusResourceDelivery\Managers;

use Nexus\Core\Support\CustomCollection;
use NexusResourceDelivery\DTOs\ResourceDeliveryCompleteDto;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;

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
    public function addContent(ResourceDeliveryContentDto $resourceDeliveryContentDto): void;

    /**
     * 複数の配送コンテンツを配送前リストにまとめて追加する
     */
    public function addContents(CustomCollection $contents): void;

    /**
     * 配送前リストからコンテンツを取得する
     *
     * @return CustomCollection<string, ResourceDeliveryContentDto>
     */
    public function getPendingContents(): CustomCollection;

    /**
     * 送信完了リストからコンテンツを取得する
     *
     * @param  string  $contentClass  コンテンツクラス名
     * @return CustomCollection<ResourceDeliveryContentDto>
     */
    public function getSendCompleteContents(string $contentClass): CustomCollection;

    /**
     * 配送が必要なコンテンツがあるかチェック
     */
    public function hasPendingContents(): bool;

    /**
     * 配送処理を実行した後に実行する処理
     *
     * @param  ResourceDeliveryCompleteDto  $resourceDeliveryCompleteDto  送信完了データ
     */
    public function afterSend(ResourceDeliveryCompleteDto $resourceDeliveryCompleteDto): void;
}
