<?php

namespace App\Domain\Delivery\Managers;

use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\DTOs\DeliveryComplete;
use Illuminate\Support\Collection;

/**
 * DeliveryManager
 *
 * 配送コンテンツの管理を行うクラス
 *
 * - 配送前のコンテンツと、送信完了のコンテンツを管理する
 * - 配送前のコンテンツの配送は、DeliveryServiceで実行する
 *
 * 責務：
 * 1. 配送前コンテンツの保持（needToSendContents）
 * 2. 送信完了コンテンツの保持（sendCompleteContents）
 * 3. 配送後の状態遷移処理
 */
class DeliveryManager implements DeliveryManagerInterface
{
    /**
     * key: DeliveryContent.uniqueId
     * value: DeliveryContent
     *
     * 配送前のコンテンツを格納する
     *
     * @var array<string, DeliveryContent>
     */
    private array $needToSendContents;

    /**
     * key: DeliveryContentクラス名
     * value: array<DeliveryContent>
     *
     * 送信完了のコンテンツを格納する
     * APIレスポンスなどで取得しやすいように、クラスごとに分けた連想配列で保持する
     *
     * 即時配布はしていないが、メールボックスへ送信したコンテンツなどもここに含める
     *
     * @var array<string, array<DeliveryContent>>
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
     * @param DeliveryContent $content
     * @return void
     */
    public function addContent(DeliveryContent $content): void
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
     * @param Collection $contents
     * @return void
     */
    public function addContents(Collection $contents): void
    {
        foreach ($contents as $content) {
            $this->addContent($content);
        }
    }

    /**
     * 配送前リストからコンテンツを取得する
     *
     * @return Collection<string, DeliveryContent>
     *   key: DeliveryContent.uniqueId, value: DeliveryContent
     */
    public function getPendingContents(): Collection
    {
        return collect($this->needToSendContents);
    }

    /**
     * 送信完了リストからコンテンツを取得する
     *
     * @param string $contentClass コンテンツクラス名
     * @return Collection<DeliveryContent>
     */
    public function getSendCompleteContents(string $contentClass): Collection
    {
        return collect($this->sendCompleteContents[$contentClass] ?? []);
    }

    /**
     * 配送処理を実行した後に実行する処理をまとめたメソッド
     *
     * @param DeliveryComplete $sendCompleteData 送信完了データ
     * @return void
     */
    public function afterSend(DeliveryComplete $sendCompleteData): void
    {
        foreach ($sendCompleteData->getContents() as $content) {
            $this->addSendCompleteContent($content);
        }
    }

    /**
     * 配送実行済みのコンテンツを、送信完了ステータスへ変更する
     * 配送前リストから削除し、送信完了リストへ整形して追加する
     *
     * @param DeliveryContent $content
     * @return void
     */
    private function addSendCompleteContent(DeliveryContent $content): void
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
