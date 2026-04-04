<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxDiamond;
use App\Models\Trx\TrxDiamondBalance;
use App\Models\Trx\TrxInAppPurchase;
use App\Repositories\Trx\TrxDiamondBalanceRepository;
use App\Repositories\Trx\TrxDiamondRepository;
use App\Repositories\Trx\TrxInAppPurchaseRepository;
use App\Persistence\ApiSession;
use Carbon\CarbonImmutable;

/**
 * DiamondService
 * 
 * ダイヤモンド購入時のトランザクション処理を担当するサービス
 */
class DiamondService
{
    public function __construct(
        private readonly TrxDiamondRepository $trxDiamondRepository,
        private readonly TrxDiamondBalanceRepository $trxDiamondBalanceRepository,
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
        private readonly ValidationService $validationService,
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * ダイヤモンド購入処理
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param string $platform プラットフォーム（Apple, Google）
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay等）
     * @param float $unitPrice 単価（返金計算用）
     * @return array{paid_diamond_amount: int, total_paid_diamond_amount: int, total_free_diamond_amount: int}
     */
    public function purchaseDiamond(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        float $unitPrice
    ): array {
        // 1. 購入履歴を取得（Repository経由）
        $purchaseHistory = $this->trxInAppPurchaseRepository->findByBillingPlatformAndMstInAppPurchaseId(
            $billingPlatform,
            $mstInAppPurchase->getId()
        );

        // 2. 購入制限チェック
        $this->validationService->validatePurchaseLimit($mstInAppPurchase, $purchaseHistory, $billingPlatform);

        // 3. ダイヤモンド現在値を取得または作成（Repository経由）
        $diamond = $this->trxDiamondRepository->selectByPlatform($sysPlayerId, $platform);

        if ($diamond === null) {
            $diamond = new TrxDiamond([
                'sys_player_id' => $sysPlayerId,
                'platform' => $platform,
                'paid_amount' => 0,
                'free_amount' => 0,
            ]);
            $diamond->exists = false; // INSERT として認識
        }

        // 4. ダイヤモンド加算
        $diamond->setPaidAmount($diamond->getPaidAmount() + $mstInAppPurchase->getPaidDiamondAmount());
        $this->trxDiamondRepository->setModel($diamond);

        // 5. ダイヤモンド残高レコード追加（FIFO用）
        $balance = new TrxDiamondBalance([
            'sys_player_id' => $sysPlayerId,
            'platform' => $platform,
            'billing_platform' => $billingPlatform,
            'current_amount' => $mstInAppPurchase->getPaidDiamondAmount(),
            'purchase_amount' => $mstInAppPurchase->getPaidDiamondAmount(),
            'unit_price' => $unitPrice,
        ]);
        $this->trxDiamondBalanceRepository->setModel($balance);

        // 6. 購入履歴を更新
        $this->updatePurchaseHistory(
            $sysPlayerId,
            $billingPlatform,
            $mstInAppPurchase,
            $purchaseHistory
        );

        return [
            'paid_diamond_amount' => $mstInAppPurchase->getPaidDiamondAmount(),
            'total_paid_diamond_amount' => $diamond->getPaidAmount(),
            'total_free_diamond_amount' => $diamond->getFreeAmount(),
        ];
    }

    /**
     * ダイヤモンドを加算（有償/無償）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @param int $amount 加算する数量
     * @param bool $isPaid 有償ダイヤモンドか（falseの場合は無償）
     * @return void
     */
    public function addDiamond(int $sysPlayerId, string $platform, int $amount, bool $isPaid = false): void
    {
        
        // ダイヤモンドを取得または作成（Repository経由）
        $diamond = $this->trxDiamondRepository->selectByPlatform($sysPlayerId, $platform);

        if ($diamond) {
            // 既存レコードがある場合は加算
            if ($isPaid) {
                $diamond->setPaidAmount($diamond->getPaidAmount() + $amount);
            } else {
                $diamond->setFreeAmount($diamond->getFreeAmount() + $amount);
            }
        } else {
            // 新規レコードを作成
            $diamond = new TrxDiamond([
                'sys_player_id' => $sysPlayerId,
                'platform' => $platform,
                'paid_amount' => $isPaid ? $amount : 0,
                'free_amount' => $isPaid ? 0 : $amount,
            ]);
            $diamond->exists = false; // INSERT として認識
        }

        // setModelで内部キューに溜め込む
        $this->trxDiamondRepository->setModel($diamond);
    }

    /**
     * ダイヤモンドを消費（無償 → 有償の順で消費、またはは有償のみ）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $amount 消費する数量
     * @param bool $isPaidOnly 有償ダイヤのみを消費するか（falseの場合は無償→有償の順）
     * @return void
     * @throws \Exception 残高不足の場合
     */
    public function consumeDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly = false): void
    {
        // プラットフォームを取得（ApiSessionから、またはデフォルト）
        // ここでは簡易的に全プラットフォームの合計残高から消費する実装にします
        $diamonds = TrxDiamond::where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->get();

        if ($diamonds->isEmpty()) {
            throw new \Exception("ダイヤモンド残高が不足しています。必要: {$amount}, 現在: 0");
        }

        // 合計残高を計算
        $totalFree = $diamonds->sum('free_amount');
        $totalPaid = $diamonds->sum('paid_amount');

        if ($isPaidOnly) {
            // 有償ダイヤのみ消費
            if ($totalPaid < $amount) {
                throw new \Exception("有償ダイヤモンド残高が不足しています。必要: {$amount}, 現在: {$totalPaid}");
            }

            // 有償ダイヤから消費
            $remaining = $amount;
            foreach ($diamonds as $diamond) {
                if ($remaining <= 0) break;

                $paidAmount = $diamond->getPaidAmount();
                if ($paidAmount <= 0) continue;

                $consume = min($paidAmount, $remaining);
                $diamond->setPaidAmount($paidAmount - $consume);
                $this->trxDiamondRepository->setModel($diamond);
                $remaining -= $consume;
            }
        } else {
            // 無償 → 有償の順で消費
            if ($totalFree + $totalPaid < $amount) {
                $total = $totalFree + $totalPaid;
                throw new \Exception("ダイヤモンド残高が不足しています。必要: {$amount}, 現在: {$total}");
            }

            $remaining = $amount;

            // まず無償ダイヤから消費
            foreach ($diamonds as $diamond) {
                if ($remaining <= 0) break;

                $freeAmount = $diamond->getFreeAmount();
                if ($freeAmount <= 0) continue;

                $consume = min($freeAmount, $remaining);
                $diamond->setFreeAmount($freeAmount - $consume);
                $this->trxDiamondRepository->setModel($diamond);
                $remaining -= $consume;
            }

            // 無償ダイヤで足りない場合は有償ダイヤから消費
            if ($remaining > 0) {
                foreach ($diamonds as $diamond) {
                    if ($remaining <= 0) break;

                    $paidAmount = $diamond->getPaidAmount();
                    if ($paidAmount <= 0) continue;

                    $consume = min($paidAmount, $remaining);
                    $diamond->setPaidAmount($paidAmount - $consume);
                    $this->trxDiamondRepository->setModel($diamond);
                    $remaining -= $consume;
                }
            }
        }
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
