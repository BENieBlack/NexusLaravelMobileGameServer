<?php

namespace NexusResourceDelivery\Services;

use NexusResource\DTOs\ResourceDto;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;
use NexusResourceDelivery\DTOs\ResourceDeliveryCompleteDto;
use NexusResourceDelivery\DTOs\ResourceDeliveryPolicyDto;
use NexusResourceDelivery\DTOs\ResourceDeliverySummaryDto;
use NexusResourceDelivery\Handlers\ResourceDeliveryHandlerInterface;
use NexusResourceDelivery\Managers\ResourceDeliveryManagerInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
    ) {
    }

    /**
     * Handlerを登録
     *
     * @param ResourceDeliveryHandlerInterface $handler
     * @return void
     */
    public function registerHandler(ResourceDeliveryHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * リソースを追加する（単一）
     * 実際の配送はdeliver()で実行する
     *
     * @param ResourceDto $resource リソース
     * @return void
     */
    public function addResource(ResourceDto $resource): void
    {
        $content = ResourceDeliveryContentDto::fromResource($resource);
        $this->deliveryManager->addContent($content);
    }

    /**
     * リソースを追加する（複数）
     * 実際の配送はdeliver()で実行する
     *
     * @param Collection|array $resources リソースのリスト
     * @return void
     */
    public function addResources(Collection|array $resources): void
    {
        $collection = $resources instanceof Collection ? $resources : collect($resources);
        
        $contents = $collection->map(function ($resource) {
            return ResourceDeliveryContentDto::fromResource($resource);
        });

        $this->deliveryManager->addContents($contents);
    }

    /**
     * 配送コンテンツを直接追加する（単一）
     *
     * @param ResourceDeliveryContentDto $content
     * @return void
     */
    public function addContent(ResourceDeliveryContentDto $content): void
    {
        $this->deliveryManager->addContent($content);
    }

    /**
     * 配送コンテンツを直接追加する（複数）
     *
     * @param Collection|array $contents
     * @return void
     */
    public function addContents(Collection|array $contents): void
    {
        $collection = $contents instanceof Collection ? $contents : collect($contents);
        $this->deliveryManager->addContents($collection);
    }

    /**
     * DeliveryManagerに登録されたリソースを配送する
     * addResources()で登録されたリソースをまとめて配送する（遅延配送パターン）
     *
     * @param int $sysPlayerId プレイヤーID
     * @param ResourceDeliveryPolicy|null $policy 配送ポリシー（nullの場合はデフォルト）
     * @return ResourceDeliverySummary 配送結果のサマリー
     * @throws \Exception
     */
    public function deliver(
        int $sysPlayerId,
        ?ResourceDeliveryPolicyDto $policy = null,
    ): ResourceDeliverySummaryDto {
        if ($policy === null) {
            $policy = ResourceDeliveryPolicyDto::createDefaultPolicy();
        }

        try {
            $summary = $this->execDelivery(
                sysPlayerId: $sysPlayerId,
                policy: $policy,
            );
        } catch (\Throwable $e) {
            Log::error('ResourceDeliveryService::deliver failed', [
                'player_id' => $sysPlayerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // 配送結果をチェックして、必要に応じて例外を投げる
        $this->checkAndThrowErrorBySummary($summary, $policy);

        return $summary;
    }

    /**
     * 配送結果をチェックして、必要に応じて例外を投げる
     *
     * @param ResourceDeliverySummaryDto $summary
     * @param ResourceDeliveryPolicyDto $policy
     * @return void
     * @throws \Exception
     */
    private function checkAndThrowErrorBySummary(
        ResourceDeliverySummaryDto $summary,
        ResourceDeliveryPolicyDto $policy,
    ): void {
        $throwErrorTypes = $policy->getResourceTypesOfThrowErrorWhenResourceLimitReached(
            $this->getSupportedTypes(),
        );

        if ($summary->hasResourceOverflow($throwErrorTypes)) {
            $policy->throwResourceLimitReachedExceptionIfSet();
        }
    }

    /**
     * 配送処理を実行する内部メソッド
     * 追加リソースの連鎖に対応するため、最大2回ループする
     *
     * @param int $sysPlayerId
     * @param ResourceDeliveryPolicyDto $policy
     * @return ResourceDeliverySummaryDto
     */
    private function execDelivery(
        int $sysPlayerId,
        ResourceDeliveryPolicyDto $policy,
    ): ResourceDeliverySummaryDto {
        $summary = new ResourceDeliverySummaryDto();

        // 最大2回ループ（追加リソースの連鎖に対応）
        for ($i = 0; $i < 2; $i++) {
            if ($this->deliveryManager->hasPendingContents() === false) {
                break;
            }

            $iterationSummary = $this->execDeliveryIteration($sysPlayerId, $policy);
            $summary->merge($iterationSummary);
        }

        return $summary;
    }

    /**
     * 配送処理の1回分の実行
     *
     * @param int $sysPlayerId
     * @param ResourceDeliveryPolicyDto $policy
     * @return ResourceDeliverySummaryDto
     */
    private function execDeliveryIteration(
        int $sysPlayerId,
        ResourceDeliveryPolicyDto $policy,
    ): ResourceDeliverySummaryDto {
        $summary = new ResourceDeliverySummaryDto();

        $pendingContents = $this->deliveryManager->getPendingContents();

        // タイプごとにグループ化
        $groupedContents = $pendingContents->groupBy(fn($content) => $content->getTypeValue());

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
            $complete = new ResourceDeliveryCompleteDto($contents);
            $this->deliveryManager->afterSend($complete);
            $summary->addContents($contents);
        }

        return $summary;
    }

    /**
     * リソースタイプに対応するHandlerを検索
     *
     * @param string $type
     * @return ResourceDeliveryHandlerInterface|null
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
    public function getSupportedTypes(): array
    {
        $types = [];

        foreach ($this->handlers as $handler) {
            // 全てのResourceTypeに対してチェック
            foreach (\NexusResource\Enums\ResourceType::all() as $typeValue) {
                if ($handler->supports($typeValue)) {
                    $types[] = $typeValue;
                }
            }
        }

        return array_unique($types);
    }

    /**
     * 配送前のコンテンツをプレビュー（変換後の状態で取得）
     * チュートリアルガチャの引き直し機能などで使用
     *
     * @return Collection<ResourceDeliveryContentDto>
     */
    public function getConvertedContentsWithoutSend(): Collection
    {
        // TODO: 変換機能の実装が必要
        return $this->deliveryManager->getPendingContents();
    }
}
