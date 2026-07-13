<?php

namespace App\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Constants\InAppPurchaseConst;
use App\Domain\Item\Services\ItemService;
use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxDiamond;
use App\Models\Trx\TrxInAppPurchase;
use App\Models\Trx\TrxUnit;
use App\Repositories\Trx\TrxDiamondRepository;
use App\Repositories\Trx\TrxInAppPurchaseRepository;
use App\Repositories\Trx\TrxUnitRepository;
use NexusUtilities\ClockUtility;
use Carbon\CarbonImmutable;

/**
 * PackService
 * 
 * Pack商品購入時のトランザクション処理を担当するサービス
 * Pack商品はダイヤモンド、アイテム、ユニットを含むことができる
 */
class PackService
{
    public function __construct(
        private readonly TrxDiamondRepository $trxDiamondRepository,
        private readonly ItemService $itemService,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
        private readonly ValidationService $validationService,
    ) {
    }

    /**
     * Pack購入処理
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param string $platform プラットフォーム（Apple, Google）
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay等）
     * @return array{contents: array, total_free_diamond_amount: int}
     */
    public function purchasePack(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform
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
                    // 無償ダイヤモンドを付与
                    $totalFreeDiamond += $this->grantFreeDiamond($sysPlayerId, $platform, $content->getAmount());
                    $grantedContentArray[] = [
                        'type' => 'FreeDiamond',
                        'amount' => $content->getAmount(),
                    ];
                    break;

                case InAppPurchaseConst::CONTENT_TYPE_ITEM:
                    // アイテムを付与
                    $this->itemService->addItem($sysPlayerId, $content->getContentId(), $content->getAmount());
                    $grantedContentArray[] = [
                        'type' => 'Item',
                        'item_id' => $content->getContentId(),
                        'amount' => $content->getAmount(),
                    ];
                    break;

                case InAppPurchaseConst::CONTENT_TYPE_UNIT:
                    // ユニットを付与
                    $unitId = $this->grantUnit($sysPlayerId, $content->getContentId(), $content->getAmount());
                    $grantedContentArray[] = [
                        'type' => 'Unit',
                        'unit_id' => $unitId,
                        'mst_unit_id' => $content->getContentId(),
                        'amount' => $content->getAmount(),
                    ];
                    break;
            }
        }

        // 5. 購入履歴を更新
        $this->updatePurchaseHistory(
            $sysPlayerId,
            $billingPlatform,
            $mstInAppPurchase,
            $purchaseHistory
        );

        return [
            'contents' => $grantedContentArray,
            'total_free_diamond_amount' => $totalFreeDiamond,
        ];
    }

    /**
     * 無償ダイヤモンドを付与
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム
     * @param int $amount 付与数
     * @return int 付与後の合計無償ダイヤモンド数
     */
    private function grantFreeDiamond(int $sysPlayerId, string $platform, int $amount): int
    {
        $diamond = TrxDiamond::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('platform', $platform)
            ->first();

        if ($diamond === null) {
            $diamond = new TrxDiamond([
                'sys_player_id' => $sysPlayerId,
                'platform' => $platform,
                'paid_amount' => 0,
                'free_amount' => $amount,
            ]);
        } else {
            $diamond->setFreeAmount($diamond->getFreeAmount() + $amount);
        }

        $this->trxDiamondRepository->setModel($diamond);

        return $diamond->getFreeAmount();
    }

    /**
     * ユニットを付与
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstUnitId ユニットマスターID
     * @param int $count 付与数
     * @return int 付与されたユニットのID
     */
    private function grantUnit(int $sysPlayerId, string $mstUnitId, int $count = 1): int
    {
        // 複数個の場合も最初の1つのIDを返す（通常はcount=1）
        $firstUnitId = null;

        for ($i = 0; $i < $count; $i++) {
            $unit = new TrxUnit([
                'sys_player_id' => $sysPlayerId,
                'mst_unit_id' => $mstUnitId,
                'level' => 1,
                'exp' => 0,
                'created_at' => ClockUtility::now(),
                'updated_at' => ClockUtility::now(),
            ]);

            $this->trxUnitRepository->setModel($unit);

            if ($firstUnitId === null) {
                $firstUnitId = $unit->getId();
            }
        }

        return $firstUnitId;
    }

    /**
     * 購入履歴を更新
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $billingPlatform 決済プラットフォーム
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param TrxInAppPurchase|null $purchaseHistory 既存の購入履歴
     * @return void
     */
    private function updatePurchaseHistory(
        int $sysPlayerId,
        string $billingPlatform,
        MstInAppPurchase $mstInAppPurchase,
        ?TrxInAppPurchase $purchaseHistory
    ): void {
        if ($purchaseHistory === null) {
            // 初回購入の場合は新規作成
            $purchaseHistory = new TrxInAppPurchase([
                'sys_player_id' => $sysPlayerId,
                'billing_platform' => $billingPlatform,
                'mst_in_app_purchase_id' => $mstInAppPurchase->getId(),
                'total_purchase_count' => 1,
                'purchase_count' => 1,
                'purchase_count_reset_at' => $mstInAppPurchase->getPurchaseLimitReset() !== 'None' ? CarbonImmutable::now() : null,
            ]);
            $this->trxInAppPurchaseRepository->setModel($purchaseHistory);
            return;
        }

        // リセットが必要かチェック
        $newResetDate = $this->validationService->getNewResetDateIfNeeded(
            $mstInAppPurchase->getPurchaseLimitReset(),
            $purchaseHistory->getPurchaseCountResetAt()
        );

        if ($newResetDate !== null) {
            // リセットが必要な場合
            $purchaseHistory->setPurchaseCount(1);
            $purchaseHistory->setPurchaseCountResetAt($newResetDate);
        } else {
            // リセット不要の場合
            $purchaseHistory->setPurchaseCount($purchaseHistory->getPurchaseCount() + 1);
        }

        $purchaseHistory->setTotalPurchaseCount($purchaseHistory->getTotalPurchaseCount() + 1);
        $this->trxInAppPurchaseRepository->setModel($purchaseHistory);
    }
}
