<?php

namespace App\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Constants\InAppPurchaseConst;
use App\Domain\Item\Contracts\ItemInventoryInterface;
use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Trx\TrxInAppPurchaseRepository;
use App\Repositories\Trx\TrxUnitRepository;

/**
 * InAppPurchasePackService
 *
 * Pack商品購入時のトランザクション処理を担当するサービス
 * Pack商品はダイヤモンド、アイテム、ユニットを含むことができる
 */
class InAppPurchasePackService
{
    public function __construct(
        private readonly ItemInventoryInterface $itemInventory,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly InAppPurchaseValidationService $validationService,
        private readonly InAppPurchaseHistoryService $purchaseHistoryService,
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
    ) {}

    /**
     * Pack購入処理
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay等）
     * @param  string  $transactionId  プラットフォーム固有のトランザクションID
     * @return array{contents: array<int, array<string, mixed>>}
     */
    public function purchasePack(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        string $transactionId
    ): array {
        // 1. 購入履歴を取得
        $trxInAppPurchase = $this->trxInAppPurchaseRepository->selectByBillingPlatformAndMstInAppPurchaseId(
            $billingPlatform,
            $mstInAppPurchase->getId()
        );

        // 2. 購入制限チェック
        $this->validationService->validatePurchaseLimit($mstInAppPurchase, $trxInAppPurchase, $billingPlatform);

        // 3. Pack商品のコンテンツを取得
        $contentCollection = $mstInAppPurchase->contents;
        $grantedContentArray = [];

        // 4. 各コンテンツを付与
        foreach ($contentCollection as $content) {
            switch ($content->getContentType()) {
                case InAppPurchaseConst::CONTENT_TYPE_ITEM:
                    // アイテムを付与（無償ダイヤも item + free_diamond として同じ経路で trx_item に加算される）
                    $this->itemInventory->addItem($sysPlayerId, $content->getContentMstId(), $content->getAmount());
                    $grantedContentArray[] = [
                        'type' => 'item',
                        'item_id' => $content->getContentMstId(),
                        'amount' => $content->getAmount(),
                    ];
                    break;

                case InAppPurchaseConst::CONTENT_TYPE_UNIT:
                    // ユニットを付与
                    // trx_unit.id はフラッシュまで採番されないので応答には載せない。
                    // アイテムと同じく、何がいくつ付いたかはマスターIDと個数で表す
                    $this->grantUnit($sysPlayerId, $content->getContentMstId(), $content->getAmount());
                    $grantedContentArray[] = [
                        'type' => 'unit',
                        'mst_unit_id' => $content->getContentMstId(),
                        'amount' => $content->getAmount(),
                    ];
                    break;
            }
        }

        // 5. 購入履歴を更新
        $this->purchaseHistoryService->updateOrCreatePurchaseHistory(
            $sysPlayerId,
            $billingPlatform,
            $mstInAppPurchase,
            $trxInAppPurchase,
            $transactionId
        );

        return [
            'contents' => $grantedContentArray,
        ];
    }

    /**
     * ユニットを付与
     *
     * 組み立てはRepositoryに任せる。ここで new TrxUnit() を書くと、
     * grade の初期値や level_exp の列名を取りこぼす。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstUnitId  ユニットマスターID
     * @param  int  $count  付与数
     */
    private function grantUnit(int $sysPlayerId, string $mstUnitId, int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->trxUnitRepository->insertUnitForPlayer($sysPlayerId, $mstUnitId);
        }
    }
}
