<?php

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\DTOs\DeliveryComplete;
use App\Domain\Delivery\DTOs\DeliveryPolicy;
use App\Domain\Delivery\DTOs\DeliverySummary;
use App\Domain\Delivery\Enums\DeliveryMethod;
use App\Domain\Delivery\Handlers\_BaseDeliveryHandlerInterface;
use App\Domain\Delivery\Handlers\DiamondDeliveryHandler;
use App\Domain\Delivery\Handlers\EquipmentDeliveryHandler;
use App\Domain\Delivery\Handlers\ItemDeliveryHandler;
use App\Domain\Delivery\Handlers\UnitDeliveryHandler;
use App\Domain\Delivery\Handlers\WalletDeliveryHandler;
use App\Domain\Delivery\Managers\DeliveryManagerInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * DeliveryService
 *
 * ゲーム内報酬の配送処理を統括するサービス
 * Item, Unit, Equipment, Diamond, Wallet通貨を統一的に扱う
 *
 * Strategy Pattern: 各リソースタイプごとにHandlerを持ち、適切なHandlerに処理を振り分け
 *
 * 機能:
 * - DeliveryManagerとの連携による状態管理（遅延配送パターン）
 * - 報酬変換機能（重複検知と自動変換）
 * - メールボックス連携
 * - ポリシーベースのエラーハンドリング
 * - 追加報酬の連鎖処理
 *
 * 使用パターン:
 * 1. addContents() で配送コンテンツを登録
 * 2. deliver() で実際に配送を実行
 */
class DeliveryService
{
    /**
     * @var array<_BaseDeliveryHandlerInterface> Handlerのリスト
     */
    private array $handlerArray = [];

    public function __construct(
        ItemDeliveryHandler $itemHandler,
        UnitDeliveryHandler $unitHandler,
        EquipmentDeliveryHandler $equipmentHandler,
        DiamondDeliveryHandler $diamondHandler,
        WalletDeliveryHandler $walletHandler,
        private readonly DeliveryManagerInterface $deliveryManager,
    ) {
        // Handlerを登録
        $this->handlerArray = [
            $itemHandler,
            $unitHandler,
            $equipmentHandler,
            $diamondHandler,
            $walletHandler,
        ];
    }

    /**
     * 配送コンテンツを追加する（単一）
     * 実際の配送はdeliver()で実行する
     *
     * @param DeliveryContent $content 配送コンテンツ
     * @return void
     */
    public function addContent(DeliveryContent $content): void
    {
        $this->deliveryManager->addContent($content);
    }

    /**
     * 配送コンテンツを追加する（複数）
     * 実際の配送はdeliver()で実行する
     *
     * @param Collection|array $contents 配送コンテンツのリスト
     * @return void
     */
    public function addContents(Collection|array $contents): void
    {
        $collection = $contents instanceof Collection ? $contents : collect($contents);
        $this->deliveryManager->addContents($collection);
    }

    /**
     * DeliveryManagerに登録されたコンテンツを配送する
     * addContents()で登録されたコンテンツをまとめて配送する（遅延配送パターン）
     *
     * @param int $sysPlayerId プレイヤーID
     * @param DeliveryPolicy|null $policy 配送ポリシー（nullの場合はデフォルト）
     * @return DeliverySummary 配送結果のサマリー
     * @throws \Exception
     */
    public function deliver(
        int $sysPlayerId,
        ?DeliveryPolicy $policy = null,
    ): DeliverySummary {
        if ($policy === null) {
            $policy = DeliveryPolicy::createDefaultPolicy();
        }

        try {
            $summary = $this->execDelivery(
                sysPlayerId: $sysPlayerId,
                policy: $policy,
            );
        } catch (\Throwable $e) {
            // TODO: リソース上限超過の例外処理を追加
            throw $e;
        }

        // 配送結果をチェックして、必要に応じて例外を投げる
        $this->checkAndThrowErrorBySummary($summary, $policy);

        return $summary;
    }

    /**
     * 配送結果をチェックして、必要に応じて例外を投げる
     *
     * @param DeliverySummary $summary
     * @param DeliveryPolicy $policy
     * @return void
     * @throws \Exception
     */
    private function checkAndThrowErrorBySummary(
        DeliverySummary $summary,
        DeliveryPolicy $policy,
    ): void {
        $throwErrorTypes = $policy->getContentTypesOfThrowErrorWhenResourceLimitReached(
            $this->getSupportedTypes(),
        );

        if ($summary->hasResourceOverflow($throwErrorTypes)) {
            $policy->throwResourceLimitReachedExceptionIfSet();
        }
    }

    /**
     * 配送処理を実行する内部メソッド
     * 追加報酬の連鎖に対応するため、最大2回ループする
     *
     * @param int $sysPlayerId
     * @param DeliveryPolicy $policy
     * @return DeliverySummary
     */
    private function execDelivery(
        int $sysPlayerId,
        DeliveryPolicy $policy,
    ): DeliverySummary {
        $summary = new DeliverySummary();

        // 追加報酬の連鎖処理に対応（最大2回ループ）
        // 例: EXP配布→レベルアップ→レベルアップ報酬
        for ($i = 0; $i < 2; $i++) {
            if ($this->deliveryManager->hasPendingContents() === false) {
                break;
            }

            $summary->merge(
                $this->execDeliveryIteration($sysPlayerId, $policy)
            );
        }

        return $summary;
    }

    /**
     * 1回分の配送処理をまとめたメソッド
     *
     * @param int $sysPlayerId
     * @param DeliveryPolicy $policy
     * @return DeliverySummary
     */
    private function execDeliveryIteration(
        int $sysPlayerId,
        DeliveryPolicy $policy,
    ): DeliverySummary {
        $summary = new DeliverySummary();

        // 前処理（報酬変換など）
        $this->beforeDelivery($sysPlayerId);

        // コンテンツタイプごとにグループ化して処理
        /** @var Collection<string, Collection<DeliveryContent>> $typeGroups */
        $typeGroups = $this->deliveryManager->getPendingContents()
            ->groupBy(fn(DeliveryContent $content) => $content->getType());

        foreach ($typeGroups as $type => $typeContents) {
            if ($typeContents->isEmpty()) {
                continue;
            }

            $handler = $this->getHandler($type);
            if ($handler === null) {
                continue;
            }

            // 配送方法を取得
            $sendMethod = $policy->getMethodByContentType($type);

            // コンテンツごとに配送を実行
            $sendCompleteContents = collect();
            foreach ($typeContents as $content) {
                try {
                    $handler->handle($sysPlayerId, $content);
                    $content->markAsSendComplete();
                    $sendCompleteContents->push($content);
                } catch (\Exception $e) {
                    // TODO: エラーハンドリングとメールボックス送信
                    Log::error('Delivery failed', [
                        'sys_player_id' => $sysPlayerId,
                        'content' => $content->toArray(),
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            // 送信完了リストへ移動
            $deliveryComplete = new DeliveryComplete($sendCompleteContents);
            $this->deliveryManager->afterSend($deliveryComplete);

            // サマリーに追加
            $summary->addContents($sendCompleteContents);

            // TODO: ログ記録
        }

        return $summary;
    }

    /**
     * 配送前の前処理をまとめたメソッド
     * 報酬変換などを実行
     *
     * @param int $sysPlayerId
     * @return void
     */
    private function beforeDelivery(int $sysPlayerId): void
    {
        $contents = $this->deliveryManager->getPendingContents();

        // TODO: 実配布用リソースへ変換
        // - 重複ユニットの変換
        // - 重複装備の変換
        // - ボックスアイテムの変換など

        // 変換後のコンテンツを再登録
        // $this->deliveryManager->addContents($contents);
    }

    /**
     * 指定されたタイプに対応するHandlerを取得
     *
     * @param string $type
     * @return _BaseDeliveryHandlerInterface|null
     */
    private function getHandler(string $type): ?_BaseDeliveryHandlerInterface
    {
        foreach ($this->handlerArray as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }
        return null;
    }

    /**
     * 指定されたコンテンツを配布用リソースへ変換したものを取得する
     * 配布は実行せず、配布リソースが何になるかを知りたい場合に使用
     *
     * 例: チュートリアルガチャの引き直し機能
     * 引き直しができるため、配布実行前に実際に何を配布するかをプレビューする必要がある
     *
     * @param int $sysPlayerId プレイヤーID
     * @param Collection<DeliveryContent> $contents 変換対象のコンテンツ
     * @return Collection<DeliveryContent> 変換後のコンテンツ
     */
    public function getConvertedContentsWithoutSend(
        int $sysPlayerId,
        Collection $contents,
    ): Collection {
        // TODO: 実装が必要
        // 現時点では報酬変換機能（重複ユニット/装備の自動変換）が未実装のため、
        // そのまま返す
        return $contents;
    }

    /**
     * 配送可能なリソースタイプのリストを取得
     *
     * @return array<string>
     */
    public function getSupportedTypes(): array
    {
        return [
            DeliveryConst::CONTENT_TYPE_ITEM,
            DeliveryConst::CONTENT_TYPE_UNIT,
            DeliveryConst::CONTENT_TYPE_EQUIPMENT,
            DeliveryConst::CONTENT_TYPE_DIAMOND,
            DeliveryConst::CONTENT_TYPE_WALLET,
        ];
    }
}
