<?php

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\DTOs\DeliveryResult;
use App\Domain\Delivery\Handlers\_BaseDeliveryHandlerInterface;
use App\Domain\Delivery\Handlers\DiamondDeliveryHandler;
use App\Domain\Delivery\Handlers\ItemDeliveryHandler;
use App\Domain\Delivery\Handlers\UnitDeliveryHandler;
use App\Domain\Delivery\Handlers\WalletDeliveryHandler;
use App\Utilities\ApiSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DeliveryService
 *
 * ゲーム内報酬の配送処理を統括するサービス
 * Item, Unit, Equipment, Diamond, Wallet通貨を統一的に扱う
 *
 * Strategy Pattern: 各リソースタイプごとにHandlerを持ち、適切なHandlerに処理を振り分け
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
        DiamondDeliveryHandler $diamondHandler,
        WalletDeliveryHandler $walletHandler,
        private readonly ApiSession $apiSession,
    ) {
        // Handlerを登録
        $this->handlerArray = [
            $itemHandler,
            $unitHandler,
            $diamondHandler,
            $walletHandler,
        ];
    }

    /**
     * 複数のコンテンツを一括配送
     *
     * @param int $sysPlayerId プレイヤーID
     * @param array<DeliveryContent> $deliveryContents 配送するコンテンツのリスト
     * @return DeliveryResult 配送結果
     */
    public function delivers(int $sysPlayerId, array $deliveryContents): DeliveryResult
    {
        $successContentArray = [];
        $failedContentArray = [];

        DB::beginTransaction();
        try {
            foreach ($deliveryContents as $content) {
                try {
                    $this->deliver($sysPlayerId, $content);
                    $successContentArray[] = $content;
                } catch (\Exception $e) {
                    Log::error('Delivery failed', [
                        'sys_player_id' => $sysPlayerId,
                        'content' => $content->toArray(),
                        'error' => $e->getMessage(),
                    ]);
                    $failedContentArray[] = [
                        'item' => $content,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // 失敗が1つでもあればロールバック
            if (count($failedContentArray) > 0) {
                DB::rollBack();
                return new DeliveryResult(
                    deliveredItemArray: [],
                    failedItemArray: $failedContentArray,
                    totalCount: count($deliveryContents),
                    successCount: 0,
                    failedCount: count($failedContentArray)
                );
            }

            DB::commit();
            return new DeliveryResult(
                deliveredItemArray: $successContentArray,
                failedItemArray: [],
                totalCount: count($deliveryContents),
                successCount: count($successContentArray),
                failedCount: 0
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delivery transaction failed', [
                'sys_player_id' => $sysPlayerId,
                'error' => $e->getMessage(),
            ]);

            return new DeliveryResult(
                deliveredItemArray: [],
                failedItemArray: array_map(fn($content) => [
                    'item' => $content,
                    'error' => $e->getMessage(),
                ], $deliveryContents),
                totalCount: count($deliveryContents),
                successCount: 0,
                failedCount: count($deliveryContents)
            );
        }
    }

    /**
     * 単一のコンテンツを配送
     *
     * @param int $sysPlayerId プレイヤーID
     * @param DeliveryContent $content 配送するコンテンツ
     * @return void
     * @throws \Exception サポートされていないタイプの場合
     */
    public function deliver(int $sysPlayerId, DeliveryContent $content): void
    {

        // 適切なHandlerを探す
        foreach ($this->handlerArray as $handler) {
            if ($handler->supports($content->type)) {
                $handler->handle($sysPlayerId, $content);
                return;
            }
        }

        // サポートされていないタイプ
        throw new \Exception("Unsupported delivery type: {$content->type}");
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
            DeliveryConst::CONTENT_TYPE_DIAMOND,
            DeliveryConst::CONTENT_TYPE_WALLET,
        ];
    }
}
