<?php

namespace NexusResourceDelivery\Managers;

use Nexus\Core\Support\CustomCollection;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryComplete;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * ResourceDeliveryManager
 *
 * リソース配送コンテンツの管理を行うクラス
 *
 * - 配送前のコンテンツと、送信完了のコンテンツを管理する
 * - 配送前のコンテンツの配送は、ResourceDeliveryServiceで実行する
 *
 * 責務：
 * 1. 配送前コンテンツの保持（needToSendContents）
 * 2. 送信完了コンテンツの保持（sendCompleteContents）
 * 3. 配送後の状態遷移処理
 */
class ResourceDeliveryManager implements ResourceDeliveryManagerInterface
{
    /**
     * key: ResourceDeliveryContent.uniqueId
     * value: ResourceDeliveryContent
     *
     * 配送前のコンテンツを格納する
     *
     * @var array<string, ResourceDeliveryContent>
     */
    private array $needToSendContents;

    /**
     * key: ResourceDeliveryContentクラス名
     * value: array<ResourceDeliveryContent>
     *
     * 送信完了のコンテンツを格納する
     * APIレスポンスなどで取得しやすいように、クラスごとに分けた連想配列で保持する
     *
     * 即時配布はしていないが、メールボックスへ送信したコンテンツなどもここに含める
     *
     * @var array<string, array<ResourceDeliveryContent>>
     */
    private array $sendCompleteContents;

    public function __construct()
    {
        $this->needToSendContents = [];
        $this->sendCompleteContents = [];
    }

    /**
     * 配送コンテンツを配送前リストに追加する
     */
    public function addContent(ResourceDeliveryContent $resourceDeliveryContentDto): void
    {
        // 無効なコンテンツ（数量が0以下）は追加しない
        if ($resourceDeliveryContentDto->isValid() === false) {
            return;
        }

        $this->needToSendContents[$resourceDeliveryContentDto->getUniqueId()] = $resourceDeliveryContentDto;
    }

    /**
     * 配送コンテンツを配送前リストにまとめて追加する
     */
    public function addContents(CustomCollection $contents): void
    {
        foreach ($contents as $content) {
            $this->addContent($content);
        }
    }

    /**
     * 配送前リストからコンテンツを取得する
     *
     * @return CustomCollection<string, ResourceDeliveryContent>
     *                                                           key: ResourceDeliveryContent.uniqueId, value: ResourceDeliveryContent
     */
    public function getPendingContents(): CustomCollection
    {
        return new CustomCollection($this->needToSendContents);
    }

    /**
     * 送信完了リストからコンテンツを取得する
     *
     * @param  string  $contentClass  コンテンツクラス名
     * @return CustomCollection<ResourceDeliveryContent>
     */
    public function findSendCompleteContents(string $contentClass): CustomCollection
    {
        return new CustomCollection($this->sendCompleteContents[$contentClass] ?? []);
    }

    /**
     * 配送処理を実行した後に実行する処理をまとめたメソッド
     *
     * @param  ResourceDeliveryComplete  $resourceDeliveryCompleteDto  送信完了データ
     */
    public function afterSend(ResourceDeliveryComplete $resourceDeliveryCompleteDto): void
    {
        foreach ($resourceDeliveryCompleteDto->getContents() as $content) {
            $this->addSendCompleteContent($content);
        }
    }

    /**
     * 配送実行済みのコンテンツを、送信完了ステータスへ変更する
     * 配送前リストから削除し、送信完了リストへ整形して追加する
     */
    private function addSendCompleteContent(ResourceDeliveryContent $resourceDeliveryContentDto): void
    {
        // 配送前リストから削除
        unset($this->needToSendContents[$resourceDeliveryContentDto->getUniqueId()]);

        // 送信完了リストへ追加
        // コンテンツクラスごとに分けた連想配列で保持する
        $this->sendCompleteContents[$resourceDeliveryContentDto::class][] = $resourceDeliveryContentDto;
    }

    /**
     * 配送が必要なコンテンツがあるかチェック
     */
    public function hasPendingContents(): bool
    {
        return count($this->needToSendContents) > 0;
    }
}
