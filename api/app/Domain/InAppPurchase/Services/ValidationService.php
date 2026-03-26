<?php

namespace App\Domain\InAppPurchase\Services;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxInAppPurchase;
use Carbon\CarbonImmutable;

/**
 * ValidationService
 * 
 * アプリ内課金商品の購入制限をチェックするサービス
 */
class ValidationService
{
    /**
     * 購入制限をチェック
     * 
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param TrxInAppPurchase|null $purchaseHistory 購入履歴（初回購入時はnull）
     * @param string $billingPlatform 決済プラットフォーム
     * @throws GameException 購入制限に引っかかった場合
     * @return void
     */
    public function validatePurchaseLimit(
        MstInAppPurchase $mstInAppPurchase,
        ?TrxInAppPurchase $purchaseHistory,
        string $billingPlatform
    ): void {
        // 購入制限がない場合はチェック不要
        if ($mstInAppPurchase->getPurchaseLimit() === null) {
            return;
        }

        // 初回購入の場合は問題なし
        if ($purchaseHistory === null) {
            return;
        }

        // リセットが必要かチェック
        $shouldReset = $this->shouldResetPurchaseCount(
            $mstInAppPurchase->getPurchaseLimitReset(),
            $purchaseHistory->getPurchaseCountResetAt()
        );

        // リセットが必要な場合は、purchase_countをリセット後としてカウント
        $currentCount = $shouldReset ? 0 : $purchaseHistory->purchase_count;

        // 購入制限チェック
        if ($currentCount >= $mstInAppPurchase->getPurchaseLimit()) {
            throw new GameException(
                GameErrorCode::PURCHASE_LIMIT_EXCEEDED,
                "Purchase limit exceeded for this product. Limit: {$mstInAppPurchase->getPurchaseLimit()}, Current: {$currentCount}"
            );
        }
    }

    /**
     * 購入回数をリセットすべきかチェック
     * 
     * @param string $resetType リセット種別（None, Daily, Weekly, Monthly）
     * @param \DateTimeInterface|null $lastResetAt 最終リセット日時
     * @return bool リセットが必要な場合true
     */
    private function shouldResetPurchaseCount(
        string $resetType,
        ?\DateTimeInterface $lastResetAt
    ): bool {
        if ($resetType === 'None' || $lastResetAt === null) {
            return false;
        }

        $now = CarbonImmutable::now();
        $lastReset = CarbonImmutable::parse($lastResetAt);

        return match ($resetType) {
            'Daily' => !$lastReset->isToday(),
            'Weekly' => $now->weekOfYear !== $lastReset->weekOfYear || $now->year !== $lastReset->year,
            'Monthly' => $now->month !== $lastReset->month || $now->year !== $lastReset->year,
            default => false,
        };
    }

    /**
     * リセットが必要な場合の新しいリセット日時を取得
     * 
     * @param string $resetType リセット種別（None, Daily, Weekly, Monthly）
     * @param \DateTimeInterface|null $lastResetAt 最終リセット日時
     * @return \DateTimeInterface|null 新しいリセット日時（リセット不要ならnull）
     */
    public function getNewResetDateIfNeeded(
        string $resetType,
        ?\DateTimeInterface $lastResetAt
    ): ?\DateTimeInterface {
        if ($this->shouldResetPurchaseCount($resetType, $lastResetAt)) {
            return CarbonImmutable::now();
        }

        return null;
    }
}
