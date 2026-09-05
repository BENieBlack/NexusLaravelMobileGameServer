<?php

namespace App\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Constants\InAppPurchaseConst;
use App\Domain\Item\Services\ItemService;
use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxInAppPurchase;
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
        private readonly InAppPurchaseDiamondBalanceService $diamondBalanceService,
        private readonly ItemService $itemService,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly InAppPurchaseValidationService $validationService,
        private readonly InAppPurchaseHistoryService $purchaseHistoryService,
    ) {}

    /**
     * Pack購入処理
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay等）
     * @param  string  $transactionId  プラットフォーム固有のトランザクションID
     * @return array{contents: array<int, array<string, mixed>>, total_free_diamond_amount: int}
     */
    public function purchasePack(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        string $transactionId
    ): array {
        // 1. 購入履歴を取得
        $purchaseHistory = TrxInAppPurchase::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('billing_platform', $billingPlatform)
            ->where('mst_in_app_purchase_id', $mstInAppPurchase->getId())
            ->first();

        // 2. 購入制限チェック
        $this->validationService->validatePurchaseLimit($mstInAppPurchase, $purchaseHistory, $billingPlatform);

        // 3. Pack商品のコンテンツを取得
        $contentCollection = $mstInAppPurchase->contents;
        $grantedContentArray = [];
        $totalFreeDiamond = 0;

        // 4. 各コンテンツを付与
        foreach ($contentCollection as $content) {
            switch ($content->getContentType()) {
                case InAppPurchaseConst::CONTENT_TYPE_FREE_DIAMOND:
                    // 無償ダイヤモンドを付与（InAppPurchaseDiamondBalanceServiceに委譲）
                    $this->diamondBalanceService->addDiamond($sysPlayerId, $platform, $content->getAmount(), isPaid: false);
                    $totalFreeDiamond += $content->getAmount();
                    $grantedContentArray[] = [
                        'type' => 'free_diamond',
                        'amount' => $content->getAmount(),
                    ];
                    break;

                case InAppPurchaseConst::CONTENT_TYPE_ITEM:
                    // アイテムを付与
                    $this->itemService->addItem($sysPlayerId, $content->getContentMstId(), $content->getAmount());
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
            $purchaseHistory,
            $transactionId
        );

        return [
            'contents' => $grantedContentArray,
            'total_free_diamond_amount' => $totalFreeDiamond,
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
