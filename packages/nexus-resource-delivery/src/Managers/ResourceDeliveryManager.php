<?php

namespace NexusResourceDelivery\Managers;

use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;
use NexusResourceDelivery\DTOs\ResourceDeliveryCompleteDto;
use NexusPersistence\Support\CustomCollection;

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
     * value: ResourceDeliveryContentDto
     *
     * 配送前のコンテンツを格納する
     *
     * @var array<string, ResourceDeliveryContentDto>
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
     * @var array<string, array<ResourceDeliveryContentDto>>
     */
    private array $sendCompleteContents;

    public function __construct()
    {
        $this->needToSendContents = [];
        $this->sendCompleteContents = [];
    }

    /**
     * 配送コンテンツを配送前リストに追加する
     *
     * @param ResourceDeliveryContentDto $content
     * @return void
     */
    public function addContent(ResourceDeliveryContentDto $content): void
    {
        // 無効なコンテンツ（数量が0以下）は追加しない
        if ($content->isValid() === false) {
            return;
        }

        $this->needToSendContents[$content->getUniqueId()] = $content;
    }

    /**
     * 配送コンテンツを配送前リストにまとめて追加する
     *
     * @param CustomCollection $contents
     * @return void
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
     *   key: ResourceDeliveryContent.uniqueId, value: ResourceDeliveryContentDto
     */
    public function getPendingContents(): CustomCollection
    {
        return new CustomCollection($this->needToSendContents);
    }

    /**
     * 送信完了リストからコンテンツを取得する
     *
     * @param string $contentClass コンテンツクラス名
     * @return CustomCollection<ResourceDeliveryContent>
     */
    public function getSendCompleteContents(string $contentClass): CustomCollection
    {
        return new CustomCollection($this->sendCompleteContents[$contentClass] ?? []);
    }

    /**
     * 配送処理を実行した後に実行する処理をまとめたメソッド
     *
     * @param ResourceDeliveryCompleteDto $sendCompleteData 送信完了データ
     * @return void
     */
    public function afterSend(ResourceDeliveryCompleteDto $sendCompleteData): void
    {
        foreach ($sendCompleteData->getContents() as $content) {
            $this->addSendCompleteContent($content);
        }
    }

    /**
     * 配送実行済みのコンテンツを、送信完了ステータスへ変更する
     * 配送前リストから削除し、送信完了リストへ整形して追加する
     *
     * @param ResourceDeliveryContentDto $content
     * @return void
     */
    private function addSendCompleteContent(ResourceDeliveryContentDto $content): void
    {
        // 配送前リストから削除
        unset($this->needToSendContents[$content->getUniqueId()]);

        // 送信完了リストへ追加
        // コンテンツクラスごとに分けた連想配列で保持する
        $this->sendCompleteContents[$content::class][] = $content;
    }

    /**
     * 配送が必要なコンテンツがあるかチェック
     *
     * @return bool
     */
    public function hasPendingContents(): bool
    {
        return count($this->needToSendContents) > 0;
    }
}
