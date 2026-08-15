<?php

namespace NexusBilling\Validators;

use NexusBilling\Constants\PurchaseLimitResetType;
use Nexus\Core\Utilities\ClockUtility;

/**
 * 購入制限チェッカー（汎用ロジック）
 * 
 * アプリ内課金商品の購入回数制限をチェックする汎用ロジックを提供
 * フレームワーク非依存、全ゲームで再利用可能
 * 
 * このクラスは判定ロジックのみを提供し、例外は投げません
 * Application層で判定結果に基づいて適切な例外処理を行います
 */
class _BasePurchaseLimitValidator
{
    /**
     * 購入制限を超えているかチェック
     * 
     * @param int|null $purchaseLimit 購入制限（nullの場合は制限なし）
     * @param int $currentPurchaseCount 現在の購入回数
     * @param string $resetType リセット種別（PurchaseLimitResetTypeの定数）
     * @param string|null $lastResetAt 最終リセット日時（Y-m-d H:i:s）
     * @return bool 制限を超えている場合true
     */
    public function isLimitExceeded(
        ?int $purchaseLimit,
        int $currentPurchaseCount,
        string $resetType,
        ?string $lastResetAt
    ): bool {
        // 購入制限がない場合は常にfalse
        if ($purchaseLimit === null) {
            return false;
        }

        // リセットが必要な場合は、カウントを0として扱う
        $effectiveCount = $this->calculateEffectiveCount(
            $currentPurchaseCount,
            $resetType,
            $lastResetAt
        );

        return $effectiveCount >= $purchaseLimit;
    }

    /**
     * 有効な購入回数を計算
     * 
     * リセットが必要な場合は0を返し、不要な場合は現在の購入回数を返す
     * 
     * @param int $currentPurchaseCount 現在の購入回数
     * @param string $resetType リセット種別（PurchaseLimitResetTypeの定数）
     * @param string|null $lastResetAt 最終リセット日時（Y-m-d H:i:s）
     * @return int 有効な購入回数
     */
    public function calculateEffectiveCount(
        int $currentPurchaseCount,
        string $resetType,
        ?string $lastResetAt
    ): int {
        if ($this->shouldResetPurchaseCount($resetType, $lastResetAt)) {
            return 0;
        }

        return $currentPurchaseCount;
    }

    /**
     * 購入回数をリセットすべきかチェック
     * 
     * @param string $resetType リセット種別（PurchaseLimitResetTypeの定数）
     * @param string|null $lastResetAt 最終リセット日時（Y-m-d H:i:s）
     * @return bool リセットが必要な場合true
     */
    public function shouldResetPurchaseCount(
        string $resetType,
        ?string $lastResetAt
    ): bool {
        // リセットなし、または初回購入の場合はリセット不要
        if ($resetType === PurchaseLimitResetType::NONE || $lastResetAt === null) {
            return false;
        }

        $lastResetAtString = $lastResetAt;

        return match ($resetType) {
            PurchaseLimitResetType::DAILY => !ClockUtility::isToday($lastResetAtString),
            PurchaseLimitResetType::WEEKLY => $this->isDifferentWeek($lastResetAtString),
            PurchaseLimitResetType::MONTHLY => $this->isDifferentMonth($lastResetAtString),
            default => false,
        };
    }

    /**
     * リセットが必要な場合の新しいリセット日時を取得
     * 
     * @param string $resetType リセット種別（PurchaseLimitResetTypeの定数）
     * @param string|null $lastResetAt 最終リセット日時（Y-m-d H:i:s）
     * @return string|null 新しいリセット日時（リセット不要ならnull）
     */
    public function getNewResetDateIfNeeded(
        string $resetType,
        ?string $lastResetAt
    ): ?string {
        if ($this->shouldResetPurchaseCount($resetType, $lastResetAt)) {
            return ClockUtility::nowToString();
        }

        return null;
    }

    /**
     * 週が異なるかチェック
     * 
     * @param string $lastResetAtString 最終リセット日時文字列
     * @return bool 週が異なる場合true
     */
    private function isDifferentWeek(string $lastResetAtString): bool
    {
        $nowYear = ClockUtility::year(ClockUtility::nowToString());
        $nowWeek = ClockUtility::weekOfYear(ClockUtility::nowToString());
        $lastYear = ClockUtility::year($lastResetAtString);
        $lastWeek = ClockUtility::weekOfYear($lastResetAtString);

        return $nowWeek !== $lastWeek || $nowYear !== $lastYear;
    }

    /**
     * 月が異なるかチェック
     * 
     * @param string $lastResetAtString 最終リセット日時文字列
     * @return bool 月が異なる場合true
     */
    private function isDifferentMonth(string $lastResetAtString): bool
    {
        $nowYear = ClockUtility::year(ClockUtility::nowToString());
        $nowMonth = ClockUtility::month(ClockUtility::nowToString());
        $lastYear = ClockUtility::year($lastResetAtString);
        $lastMonth = ClockUtility::month($lastResetAtString);

        return $nowMonth !== $lastMonth || $nowYear !== $lastYear;
    }
}
