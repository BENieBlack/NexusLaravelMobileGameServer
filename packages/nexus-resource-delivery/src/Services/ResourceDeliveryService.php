<?php

namespace NexusResourceDelivery\Services;

use Illuminate\Support\Facades\Log;
use Nexus\Core\Support\CustomCollection;
use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryComplete;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryPolicy;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliverySummary;
use NexusResourceDelivery\Handlers\ResourceDeliveryHandlerInterface;
use NexusResourceDelivery\Managers\ResourceDeliveryManagerInterface;

/**
 * ResourceDeliveryService
 *
 * ゲーム内リソースの配送処理を統括するサービス
 * Diamond, Unit, Equipment, Item, Coin など全てのリソースを統一的に扱う
 *
 * Strategy Pattern: 各リソースタイプごとにHandlerを持ち、適切なHandlerに処理を振り分け
 *
 * 機能:
 * - ResourceDeliveryManagerとの連携による状態管理（遅延配送パターン）
 * - リソース変換機能（重複検知と自動変換）
 * - メールボックス連携
 * - ポリシーベースのエラーハンドリング
 * - 追加リソースの連鎖処理
 *
 * 使用パターン:
 * 1. addResources() でリソースを登録
 * 2. deliver() で実際に配送を実行
 */
class ResourceDeliveryService
{
    /**
     * @var array<ResourceDeliveryHandlerInterface> Handlerのリスト
     */
    private array $handlers = [];

    public function __construct(
        private readonly ResourceDeliveryManagerInterface $deliveryManager,
    ) {}

    /**
     * Handlerを登録
     */
    public function registerHandler(ResourceDeliveryHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * リソースを追加する（単一）
     * 実際の配送はdeliver()で実行する
     */
    public function addResource(Resource $resource): void
    {
        $content = ResourceDeliveryContent::fromResource($resource);
        $this->deliveryManager->addContent($content);
    }

    /**
     * リソースを追加する（複数）
     * 実際の配送はdeliver()で実行する
     *
     * @param  CustomCollection<array-key, \NexusResource\DataTransferObjects\Resource>|array<array-key, \NexusResource\DataTransferObjects\Resource>  $resources  リソースのリスト
     */
    public function addResources(CustomCollection|array $resources): void
    {
        $collection = $resources instanceof CustomCollection ? $resources : new CustomCollection($resources);

        $contents = $collection->map(function ($resource) {
            return ResourceDeliveryContent::fromResource($resource);
        });

        $this->deliveryManager->addContents($contents);
    }

    /**
     * 配送コンテンツを直接追加する（単一）
     */
    public function addContent(ResourceDeliveryContent $resourceDeliveryContent): void
    {
        $this->deliveryManager->addContent($resourceDeliveryContent);
    }

    /**
     * 配送コンテンツを直接追加する（複数）
     *
     * @param  CustomCollection<array-key, ResourceDeliveryContent>|array<array-key, ResourceDeliveryContent>  $contents  配送コンテンツのリスト
     */
    public function addContents(CustomCollection|array $contents): void
    {
        $collection = $contents instanceof CustomCollection ? $contents : new CustomCollection($contents);
        $this->deliveryManager->addContents($collection);
    }

    /**
     * DeliveryManagerに登録されたリソースを配送する
     * addResources()で登録されたリソースをまとめて配送する（遅延配送パターン）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryPolicy|null  $resourceDeliveryPolicy  配送ポリシー（nullの場合はデフォルト）
     * @return ResourceDeliverySummary 配送結果のサマリー
     *
     * @throws \Exception
     */
    public function deliver(
        int $sysPlayerId,
        ?ResourceDeliveryPolicy $resourceDeliveryPolicy = null,
    ): ResourceDeliverySummary {
        if ($resourceDeliveryPolicy === null) {
            $resourceDeliveryPolicy = ResourceDeliveryPolicy::createDefaultPolicy();
        }

        try {
            $summary = $this->execDelivery(
                sysPlayerId: $sysPlayerId,
                resourceDeliveryPolicy: $resourceDeliveryPolicy,
            );
        } catch (\Throwable $e) {
            Log::error('ResourceDeliveryService::deliver failed', [
                'player_id' => $sysPlayerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // 配送結果をチェックして、必要に応じて例外を投げる
        $this->checkAndThrowErrorBySummary($summary, $resourceDeliveryPolicy);

        return $summary;
    }

    /**
     * 配送結果をチェックして、必要に応じて例外を投げる
     *
     * @throws \Exception
     */
    private function checkAndThrowErrorBySummary(
        ResourceDeliverySummary $summary,
        ResourceDeliveryPolicy $resourceDeliveryPolicy,
    ): void {
        $throwErrorTypes = $resourceDeliveryPolicy->findResourceTypesOfThrowErrorWhenResourceLimitReached(
            $this->supportedTypes(),
        );

        if ($summary->hasResourceOverflow($throwErrorTypes)) {
            $resourceDeliveryPolicy->throwResourceLimitReachedExceptionIfSet();
        }
    }

    /**
     * 配送処理を実行する内部メソッド
     * 追加リソースの連鎖に対応するため、最大2回ループする
     */
    private function execDelivery(
        int $sysPlayerId,
        ResourceDeliveryPolicy $resourceDeliveryPolicy,
    ): ResourceDeliverySummary {
        $summary = new ResourceDeliverySummary;

        // 最大2回ループ（追加リソースの連鎖に対応）
        for ($i = 0; $i < 2; $i++) {
            if ($this->deliveryManager->hasPendingContents() === false) {
                break;
            }

            $iterationSummary = $this->execDeliveryIteration($sysPlayerId, $resourceDeliveryPolicy);
            $summary->merge($iterationSummary);
        }

        return $summary;
    }

    /**
     * 配送処理の1回分の実行
     */
    private function execDeliveryIteration(
        int $sysPlayerId,
        ResourceDeliveryPolicy $resourceDeliveryPolicy,
    ): ResourceDeliverySummary {
        $summary = new ResourceDeliverySummary;

        $pendingContents = $this->deliveryManager->getPendingContents();

        // タイプごとにグループ化
        $groupedContents = $pendingContents->groupBy(fn ($content) => $content->getTypeValue());

        // 各グループに対して配送処理を実行
        foreach ($groupedContents as $type => $contents) {
            $handler = $this->findHandler($type);

            if ($handler === null) {
                Log::warning('ResourceDeliveryService: Handler not found', [
                    'type' => $type,
                    'count' => $contents->count(),
                ]);

                continue;
            }

            // 各コンテンツを配送
            foreach ($contents as $content) {
                try {
                    $handler->handle($sysPlayerId, $content);
                    $content->markAsSendComplete();
                } catch (\Throwable $e) {
                    Log::error('ResourceDeliveryService: Handler failed', [
                        'type' => $type,
                        'content' => $content->toArray(),
                        'error' => $e->getMessage(),
                    ]);
                    // エラーの場合もcompleteとしてマークして、failure_reasonを設定
                    $content->markAsSendComplete();
                }
            }

            // 送信完了として記録
            $complete = new ResourceDeliveryComplete($contents);
            $this->deliveryManager->afterSend($complete);
            $summary->addContents($contents);
        }

        return $summary;
    }

    /**
     * リソースタイプに対応するHandlerを検索
     */
    private function findHandler(string $type): ?ResourceDeliveryHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * サポートするリソースタイプの一覧を取得
     *
     * @return array<string>
     */
    public function supportedTypes(): array
    {
        $types = [];

        foreach ($this->handlers as $handler) {
            // 全てのResourceTypeに対してチェック
            foreach (ResourceType::all() as $typeValue) {
                if ($handler->supports($typeValue)) {
                    $types[] = $typeValue;
                }
            }
        }

        return array_unique($types);
    }
}
